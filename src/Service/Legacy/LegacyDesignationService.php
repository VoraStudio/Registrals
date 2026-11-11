<?php

declare(strict_types=1);

namespace App\Service\Legacy;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\Entity\Certificate;
use App\Entity\LegacyContact;
use App\Entity\User;
use App\Enum\CertificateScope;
use App\Enum\CertificateStatus;
use App\Enum\LegacyContactStatus;
use App\Repository\CertificateRepository;
use App\Repository\LegacyContactRepository;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Orquestra la designació consentida de persones de confiança (Art. 96 LOPDGDD),
 * la notificació de transparència RGPD (Art. 14), l'acceptació/rebuig i la revocació.
 */
class LegacyDesignationService
{
    public const INVITATION_TTL_DAYS = 14;
    public const PRIVACY_NOTICE_VERSION = 'v1.0';

    public function __construct(
        private readonly LegacyContactRepository $legacyContactRepository,
        private readonly CertificateRepository $certificateRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ?UserActivityLogger $activityLogger = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly string $mailerFromAddress = 'info@registrals.com',
        private readonly string $mailerFromName = 'Registrals'
    ) {
    }

    public function hasOwnerRealFullName(User $owner): bool
    {
        return trim($owner->getFirstName()) !== '' && trim($owner->getLastName()) !== '';
    }

    /**
     * @return array{allowed: bool, reason: ?string, maxSlots: int, usedSlots: int}
     */
    public function evaluateDesignationEligibility(User $owner): array
    {
        $plan = $owner->getPlan();
        $usedSlots = $this->legacyContactRepository->countOccupiedSlotsByOwner($owner);
        $maxSlots = $plan?->getMaxLegacyContacts() ?? 0;

        if ($plan === null || !$plan->isAllowLegacyDesignation()) {
            return [
                'allowed' => false,
                'reason' => 'plan_not_allowed',
                'maxSlots' => $maxSlots,
                'usedSlots' => $usedSlots,
            ];
        }

        if (!$this->hasOwnerRealFullName($owner)) {
            return [
                'allowed' => false,
                'reason' => 'missing_full_name',
                'maxSlots' => $maxSlots,
                'usedSlots' => $usedSlots,
            ];
        }

        if ($maxSlots !== -1 && $maxSlots < 9999 && $usedSlots >= $maxSlots) {
            return [
                'allowed' => false,
                'reason' => 'quota_reached',
                'maxSlots' => $maxSlots,
                'usedSlots' => $usedSlots,
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
            'maxSlots' => $maxSlots,
            'usedSlots' => $usedSlots,
        ];
    }

    public function designate(
        User $owner,
        string $fullName,
        string $email,
        ?string $relationship,
        ?string $clientIp
    ): LegacyContact {
        $eligibility = $this->evaluateDesignationEligibility($owner);
        if (!$eligibility['allowed']) {
            throw new DomainException(match ($eligibility['reason']) {
                'plan_not_allowed' => 'La designació de persona de confiança està reservada als titulars amb Pla Plus o superior.',
                'missing_full_name' => 'Has d\'informar el teu nom i cognoms reals al perfil abans de designar una persona de confiança.',
                'quota_reached' => sprintf('Has assolit el màxim de %d persona(es) de confiança actives o pendents del teu pla.', $eligibility['maxSlots']),
                default => 'El teu compte no reuneix les condicions necessàries per realitzar aquesta designació.',
            });
        }

        $cleanName = trim($fullName);
        if ($cleanName === '') {
            throw new DomainException('Cal indicar el nom complet de la persona de confiança.');
        }

        $normalizedEmail = mb_strtolower(trim($email));
        if ($normalizedEmail === '' || !filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('L\'adreça electrònica indicada no és vàlida.');
        }

        if ($normalizedEmail === mb_strtolower(trim($owner->getEmail()))) {
            throw new DomainException('No et pots designar a tu mateix/a com a persona de confiança.');
        }

        foreach ($this->legacyContactRepository->findByOwnerOrdered($owner) as $existing) {
            if ($existing->getEmail() === $normalizedEmail && !$existing->getStatus()->isTerminated()) {
                throw new DomainException('Aquesta adreça ja té una designació activa o pendent de resposta.');
            }
        }

        $rawToken = bin2hex(random_bytes(32));
        $expiresAt = (new \DateTimeImmutable())->modify(sprintf('+%d days', self::INVITATION_TTL_DAYS));

        $contact = new LegacyContact(
            owner: $owner,
            fullName: $cleanName,
            email: $normalizedEmail,
            relationship: $relationship !== null && trim($relationship) !== '' ? trim($relationship) : null,
            ownerConsentIp: $clientIp,
            privacyNoticeVersion: self::PRIVACY_NOTICE_VERSION,
        );
        $contact->issueInvitationToken(hash('sha256', $rawToken), $expiresAt);

        $this->entityManager->persist($contact);
        $this->entityManager->flush();

        $this->recordAudit(
            $owner,
            UserActivityAction::LEGACY_CONTACT_DESIGNATED,
            sprintf('Designació de persona de confiança enviada a %s (Art. 96 LOPDGDD)', $normalizedEmail),
            [
                'legacy_contact_uuid' => $contact->getUuid()->toRfc4122(),
                'contact_email' => $normalizedEmail,
                'privacy_notice_version' => self::PRIVACY_NOTICE_VERSION,
                'expires_at' => $expiresAt->format(\DateTimeInterface::ATOM),
            ]
        );

        $this->sendInvitationEmail($contact, $rawToken, $expiresAt);

        return $contact;
    }

    public function resendInvitation(User $owner, LegacyContact $contact): void
    {
        if ($contact->getOwner()->getId() !== $owner->getId()) {
            throw new DomainException('No tens permisos per gestionar aquesta designació.');
        }

        if ($contact->getStatus() !== LegacyContactStatus::PENDING) {
            throw new DomainException('Només es poden reenviar invitacions que continuen pendents de resposta.');
        }

        $rawToken = bin2hex(random_bytes(32));
        $expiresAt = (new \DateTimeImmutable())->modify(sprintf('+%d days', self::INVITATION_TTL_DAYS));
        $contact->issueInvitationToken(hash('sha256', $rawToken), $expiresAt);
        $this->entityManager->flush();

        $this->sendInvitationEmail($contact, $rawToken, $expiresAt);
    }

    /**
     * Revoca la designació i invalida qualsevol certificat d'Inventari Global de Llegat vinculat.
     */
    public function revoke(User $owner, LegacyContact $contact): int
    {
        if ($contact->getOwner()->getId() !== $owner->getId()) {
            throw new DomainException('No tens permisos per revocar aquesta designació.');
        }

        if ($contact->getStatus()->isTerminated()) {
            throw new DomainException('Aquesta designació ja estava finalitzada o revocada.');
        }

        $contact->revoke();
        $revokedCount = $this->revokeLinkedLegacyCertificates($contact);
        $this->entityManager->flush();

        $this->recordAudit(
            $owner,
            UserActivityAction::LEGACY_CONTACT_REVOKED,
            sprintf('Revocació de persona de confiança %s (%d certificats invalidats)', $contact->getEmail(), $revokedCount),
            [
                'legacy_contact_uuid' => $contact->getUuid()->toRfc4122(),
                'contact_email' => $contact->getEmail(),
                'revoked_certificates' => $revokedCount,
                'initiated_by' => 'owner',
            ]
        );

        return $revokedCount;
    }

    /**
     * Retorna les designacions rebudes actives o pendents vigents per al destinatari
     * (caducant automàticament les invitacions que hagin superat el termini).
     *
     * @return list<LegacyContact>
     */
    public function getIncomingDesignations(User $recipient): array
    {
        $rows = $this->legacyContactRepository->findIncomingActiveOrPendingForUser($recipient);
        $active = [];
        $expiredUpdated = false;

        foreach ($rows as $contact) {
            if ($contact->getStatus() === LegacyContactStatus::PENDING && $contact->isInvitationExpired()) {
                $contact->setStatus(LegacyContactStatus::EXPIRED);
                $contact->setInvitationTokenHash(null);
                $expiredUpdated = true;
                continue;
            }
            $active[] = $contact;
        }

        if ($expiredUpdated) {
            $this->entityManager->flush();
        }

        return $active;
    }

    public function declineByRecipient(LegacyContact $contact, User $actor, ?string $clientIp): void
    {
        if (!$this->isRecipientActor($contact, $actor)) {
            throw new DomainException('No tens permisos per respondre aquesta designació.');
        }

        $this->decline($contact, $clientIp);
    }

    /**
     * Permet a la persona designada retirar el seu consentiment (Art. 7.3 RGPD) des del seu propi perfil.
     */
    public function renounceByBeneficiary(LegacyContact $contact, User $actor, ?string $clientIp): int
    {
        if (!$this->isRecipientActor($contact, $actor)) {
            throw new DomainException('No tens permisos per renunciar a aquesta designació.');
        }

        if ($contact->getStatus() !== LegacyContactStatus::ACCEPTED) {
            throw new DomainException('Només pots renunciar a una designació que figuri acceptada i activa.');
        }

        $contact->revoke();
        $revokedCount = $this->revokeLinkedLegacyCertificates($contact);
        $this->entityManager->flush();

        $details = [
            'legacy_contact_uuid' => $contact->getUuid()->toRfc4122(),
            'contact_email' => $contact->getEmail(),
            'revoked_certificates' => $revokedCount,
            'initiated_by' => 'beneficiary',
            'ip' => $clientIp,
        ];

        $this->recordAudit(
            $contact->getOwner(),
            UserActivityAction::LEGACY_CONTACT_REVOKED,
            sprintf('%s ha renunciat al seu consentiment com a persona de confiança (%d certificats invalidats)', $actor->getFullName(), $revokedCount),
            $details
        );

        if ($actor->getId() !== $contact->getOwner()->getId()) {
            $this->recordAudit(
                $actor,
                UserActivityAction::LEGACY_CONTACT_REVOKED,
                sprintf('Renúncia voluntària de persona de confiança designada per %s', $contact->getOwner()->getFullName()),
                $details
            );
        }

        $this->sendOwnerResponseEmail($contact, accepted: false, renounced: true);

        return $revokedCount;
    }

    /**
     * @return array{contact: ?LegacyContact, state: string}
     */
    public function resolveInvitationByRawToken(string $rawToken): array
    {
        if ($rawToken === '' || strlen($rawToken) !== 64 || !ctype_xdigit($rawToken)) {
            return ['contact' => null, 'state' => 'invalid'];
        }

        $contact = $this->legacyContactRepository->findOneBy([
            'invitationTokenHash' => hash('sha256', $rawToken),
        ]);

        if (!$contact instanceof LegacyContact) {
            return ['contact' => null, 'state' => 'invalid'];
        }

        if ($contact->getStatus() !== LegacyContactStatus::PENDING) {
            return ['contact' => $contact, 'state' => $contact->getStatus()->value];
        }

        if ($contact->isInvitationExpired()) {
            $contact->setStatus(LegacyContactStatus::EXPIRED);
            $contact->setInvitationTokenHash(null);
            $this->entityManager->flush();

            return ['contact' => $contact, 'state' => 'expired'];
        }

        return ['contact' => $contact, 'state' => 'valid'];
    }

    public function accept(LegacyContact $contact, User $actor, ?string $clientIp): void
    {
        if ($contact->getStatus() !== LegacyContactStatus::PENDING || $contact->isInvitationExpired()) {
            throw new DomainException('Aquesta invitació ja no es troba activa o ha caducat.');
        }

        if (mb_strtolower(trim($actor->getEmail())) !== $contact->getEmail()) {
            throw new DomainException(sprintf(
                'Aquesta designació està dirigida exclusivament a %s.',
                $contact->getEmail()
            ));
        }

        $contact->accept($actor, $clientIp);
        $this->entityManager->flush();

        $details = [
            'legacy_contact_uuid' => $contact->getUuid()->toRfc4122(),
            'contact_email' => $contact->getEmail(),
            'beneficiary_uuid' => $actor->getUuid()->toRfc4122(),
        ];

        $this->recordAudit(
            $contact->getOwner(),
            UserActivityAction::LEGACY_CONTACT_ACCEPTED,
            sprintf('%s ha acceptat la designació com a persona de confiança', $actor->getFullName()),
            $details
        );

        if ($actor->getId() !== $contact->getOwner()->getId()) {
            $this->recordAudit(
                $actor,
                UserActivityAction::LEGACY_CONTACT_ACCEPTED,
                sprintf('Consentiment confirmat com a persona de confiança de %s', $contact->getOwner()->getFullName()),
                $details
            );
        }

        $this->sendOwnerResponseEmail($contact, accepted: true);
    }

    public function decline(LegacyContact $contact, ?string $clientIp): void
    {
        if ($contact->getStatus() !== LegacyContactStatus::PENDING || $contact->isInvitationExpired()) {
            throw new DomainException('Aquesta invitació ja no es troba activa o ha caducat.');
        }

        $contact->decline($clientIp);
        $this->entityManager->flush();

        $this->recordAudit(
            $contact->getOwner(),
            UserActivityAction::LEGACY_CONTACT_DECLINED,
            sprintf('%s (%s) ha declinat la designació com a persona de confiança', $contact->getFullName(), $contact->getEmail()),
            [
                'legacy_contact_uuid' => $contact->getUuid()->toRfc4122(),
                'contact_email' => $contact->getEmail(),
            ]
        );

        $this->sendOwnerResponseEmail($contact, accepted: false);
    }

    private function sendInvitationEmail(LegacyContact $contact, string $rawToken, \DateTimeImmutable $expiresAt): void
    {
        $inviteUrl = $this->urlGenerator->generate(
            'app_legacy_invitation_show',
            ['token' => $rawToken],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
                ->to(new Address($contact->getEmail(), $contact->getFullName()))
                ->subject(sprintf('%s t\'ha designat com a Persona de Confiança — Registrals', $contact->getOwner()->getFullName()))
                ->htmlTemplate('emails/legacy_designation_invite.html.twig')
                ->textTemplate('emails/legacy_designation_invite.txt.twig')
                ->context([
                    'contact' => $contact,
                    'owner' => $contact->getOwner(),
                    'inviteUrl' => $inviteUrl,
                    'expiresAt' => $expiresAt,
                ]);

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger?->warning('Error en enviar invitació de persona de confiança: ' . $e->getMessage(), [
                'legacy_contact_uuid' => $contact->getUuid()->toRfc4122(),
            ]);
        }
    }

    private function revokeLinkedLegacyCertificates(LegacyContact $contact): int
    {
        $now = new \DateTimeImmutable();
        $revokedCount = 0;

        $linkedCerts = $this->certificateRepository->findBy([
            'legacyContact' => $contact,
            'scope' => CertificateScope::LEGACY_INVENTORY,
        ]);

        foreach ($linkedCerts as $certificate) {
            if ($certificate->getStatus() === CertificateStatus::VALID) {
                $certificate->setStatus(CertificateStatus::REVOKED);
                $certificate->setRevokedAt($now);
                ++$revokedCount;
            }
        }

        return $revokedCount;
    }

    private function isRecipientActor(LegacyContact $contact, User $actor): bool
    {
        return $contact->isDesignatedUser($actor);
    }

    private function sendOwnerResponseEmail(LegacyContact $contact, bool $accepted, bool $renounced = false): void
    {
        $profileUrl = $this->urlGenerator->generate(
            'app_profile',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        ) . '#herencia-digital';

        $subject = match (true) {
            $accepted => sprintf('%s ha acceptat ser la teva Persona de Confiança — Registrals', $contact->getFullName()),
            $renounced => sprintf('%s ha renunciat com a Persona de Confiança — Registrals', $contact->getFullName()),
            default => sprintf('%s ha declinat la designació de Persona de Confiança — Registrals', $contact->getFullName()),
        };

        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
                ->to(new Address($contact->getOwner()->getEmail(), $contact->getOwner()->getFullName()))
                ->subject($subject)
                ->htmlTemplate('emails/legacy_designation_response.html.twig')
                ->textTemplate('emails/legacy_designation_response.txt.twig')
                ->context([
                    'contact' => $contact,
                    'owner' => $contact->getOwner(),
                    'accepted' => $accepted,
                    'renounced' => $renounced,
                    'profileUrl' => $profileUrl,
                ]);

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger?->warning('Error en enviar confirmació al titular de llegat: ' . $e->getMessage(), [
                'legacy_contact_uuid' => $contact->getUuid()->toRfc4122(),
            ]);
        }
    }

    /**
     * @param array<string, mixed>|null $details
     */
    private function recordAudit(User $user, UserActivityAction $action, string $description, ?array $details = null): void
    {
        if ($this->activityLogger === null) {
            return;
        }

        try {
            $this->activityLogger->log($user, $action, $description, $details);
        } catch (\Throwable $e) {
            $this->logger?->warning('Error registrant auditoria d\'herència digital: ' . $e->getMessage());
        }
    }
}

