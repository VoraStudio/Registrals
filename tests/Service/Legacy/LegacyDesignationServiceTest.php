<?php

declare(strict_types=1);

namespace App\Tests\Service\Legacy;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\Entity\Certificate;
use App\Entity\LegacyContact;
use App\Entity\Plan;
use App\Entity\User;
use App\Enum\CertificateMode;
use App\Enum\CertificateScope;
use App\Enum\CertificateStatus;
use App\Enum\LegacyContactStatus;
use App\Repository\CertificateRepository;
use App\Repository\LegacyContactRepository;
use App\Service\Legacy\LegacyDesignationService;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LegacyDesignationServiceTest extends TestCase
{
    private function createService(
        ?LegacyContactRepository $contactRepo = null,
        ?CertificateRepository $certRepo = null,
        ?EntityManagerInterface $em = null,
        ?MailerInterface $mailer = null,
        ?UrlGeneratorInterface $urlGenerator = null,
        ?UserActivityLogger $auditLogger = null,
    ): LegacyDesignationService {
        $urlGenerator ??= $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://registrals.cat/llegat/acceptar/mocktoken');

        return new LegacyDesignationService(
            legacyContactRepository: $contactRepo ?? $this->createMock(LegacyContactRepository::class),
            certificateRepository: $certRepo ?? $this->createMock(CertificateRepository::class),
            entityManager: $em ?? $this->createMock(EntityManagerInterface::class),
            mailer: $mailer ?? $this->createMock(MailerInterface::class),
            urlGenerator: $urlGenerator,
            activityLogger: $auditLogger,
        );
    }

    private function createPlusOwner(bool $withRealName = true): User
    {
        $plan = (new Plan('plus', 'Pla Plus', 2499))
            ->setAllowLegacyDesignation(true)
            ->setMaxLegacyContacts(1);

        $user = (new User())
            ->setEmail('titular@registrals.cat')
            ->setFirstName($withRealName ? 'Anna' : '')
            ->setLastName($withRealName ? 'Puig' : '')
            ->setPlan($plan);

        return $user;
    }

    public function testFreePlanAndMissingNameBlockedFromDesignating(): void
    {
        $freePlan = (new Plan('free', 'Pla Free', 0))->setAllowLegacyDesignation(false);
        $freeUser = (new User())->setEmail('free@registrals.cat')->setFirstName('Joan')->setLastName('Soler')->setPlan($freePlan);

        $service = $this->createService();

        $resFree = $service->evaluateDesignationEligibility($freeUser);
        $this->assertFalse($resFree['allowed']);
        $this->assertSame('plan_not_allowed', $resFree['reason']);

        $plusNameless = $this->createPlusOwner(withRealName: false);
        $resName = $service->evaluateDesignationEligibility($plusNameless);
        $this->assertFalse($resName['allowed']);
        $this->assertSame('missing_full_name', $resName['reason']);

        $this->expectException(DomainException::class);
        $service->designate($plusNameless, 'Maria Puig', 'maria@exemple.cat', 'Germana', '127.0.0.1');
    }

    public function testSelfDesignationIsRejected(): void
    {
        $owner = $this->createPlusOwner();
        $service = $this->createService();

        $this->expectException(DomainException::class);
        $service->designate($owner, 'Anna Puig', 'TITULAR@registrals.cat', 'Jo mateixa', '127.0.0.1');
    }

    public function testQuotaLimitBlocksSecondDesignation(): void
    {
        $owner = $this->createPlusOwner();

        $repo = $this->createMock(LegacyContactRepository::class);
        $repo->method('countOccupiedSlotsByOwner')->with($owner)->willReturn(1);

        $service = $this->createService(contactRepo: $repo);

        $elig = $service->evaluateDesignationEligibility($owner);
        $this->assertFalse($elig['allowed']);
        $this->assertSame('quota_reached', $elig['reason']);

        $this->expectException(DomainException::class);
        $service->designate($owner, 'Altres Contacte', 'altre@exemple.cat', null, '127.0.0.1');
    }

    public function testDesignatePersistsHashedTokenAuditsAndSendsArt14Email(): void
    {
        $owner = $this->createPlusOwner();

        $repo = $this->createMock(LegacyContactRepository::class);
        $repo->method('countOccupiedSlotsByOwner')->willReturn(0);
        $repo->method('findByOwnerOrdered')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(LegacyContact::class));
        $em->expects($this->once())->method('flush');

        $audit = $this->createMock(UserActivityLogger::class);
        $audit->expects($this->once())->method('log')->with(
            $owner,
            UserActivityAction::LEGACY_CONTACT_DESIGNATED,
            $this->stringContains('maria@exemple.cat'),
            $this->arrayHasKey('legacy_contact_uuid')
        );

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send')->with($this->callback(function (TemplatedEmail $email): bool {
            $ctx = $email->getContext();
            $this->assertSame('emails/legacy_designation_invite.html.twig', $email->getHtmlTemplate());
            $this->assertArrayNotHasKey('items', $ctx);
            $this->assertStringContainsString('https://registrals.cat/llegat/acceptar/', (string) $ctx['inviteUrl']);

            return true;
        }));

        $service = $this->createService(contactRepo: $repo, em: $em, mailer: $mailer, auditLogger: $audit);

        $contact = $service->designate($owner, 'Maria Puig', 'MARIA@exemple.cat', 'Germana', '10.0.0.1');

        $this->assertSame('maria@exemple.cat', $contact->getEmail());
        $this->assertSame(LegacyContactStatus::PENDING, $contact->getStatus());
        $this->assertNotNull($contact->getInvitationTokenHash());
        $this->assertSame(64, strlen($contact->getInvitationTokenHash()));
    }

    public function testAcceptRejectsWrongEmailAndSucceedsForMatchingUser(): void
    {
        $owner = $this->createPlusOwner();
        $contact = new LegacyContact($owner, 'Maria Puig', 'maria@exemple.cat');
        $contact->issueInvitationToken(hash('sha256', str_repeat('a', 64)), new \DateTimeImmutable('+5 days'));

        $wrongUser = (new User())->setEmail('altre@exemple.cat')->setFirstName('Altre')->setLastName('Usuari');
        $matchingUser = (new User())->setEmail('maria@exemple.cat')->setFirstName('Maria')->setLastName('Puig');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $service = $this->createService(mailer: $mailer);

        try {
            $service->accept($contact, $wrongUser, '10.0.0.2');
            $this->fail('Expected DomainException on email mismatch.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('maria@exemple.cat', $e->getMessage());
        }

        $service->accept($contact, $matchingUser, '10.0.0.3');
        $this->assertSame(LegacyContactStatus::ACCEPTED, $contact->getStatus());
        $this->assertSame($matchingUser, $contact->getBeneficiaryUser());
    }

    public function testRevokeAlsoInvalidatesLinkedLegacyCertificates(): void
    {
        $owner = $this->createPlusOwner();
        $contact = new LegacyContact($owner, 'Maria Puig', 'maria@exemple.cat');

        $legacyCert = new Certificate(
            item: null,
            issuedBy: $owner,
            certificateNumber: 'CERT-2026-000800',
            mode: CertificateMode::OFFICIAL,
            pdfFilePath: 'certificates/2026/LEGAT.pdf',
            pdfSha256: hash('sha256', 'pdf'),
            itemFingerprintAtIssue: hash('sha256', 'fp'),
            status: CertificateStatus::VALID,
            scope: CertificateScope::LEGACY_INVENTORY,
            legacyContact: $contact,
        );

        $certRepo = $this->createMock(CertificateRepository::class);
        $certRepo->expects($this->once())->method('findBy')->willReturn([$legacyCert]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $service = $this->createService(certRepo: $certRepo, em: $em);

        $revokedCerts = $service->revoke($owner, $contact);

        $this->assertSame(1, $revokedCerts);
        $this->assertSame(LegacyContactStatus::REVOKED, $contact->getStatus());
        $this->assertSame(CertificateStatus::REVOKED, $legacyCert->getStatus());
        $this->assertNotNull($legacyCert->getRevokedAt());
    }
}
