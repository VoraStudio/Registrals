<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\SuccessionClaim;
use App\Enum\PaymentType;
use App\Infrastructure\Adapter\Payment\PaymentGatewayInterface;
use App\Repository\SuccessionClaimRepository;
use App\Service\Legacy\LegacyCertificateIssuerService;
use App\Service\Legacy\SuccessionClaimWorkflowService;
use App\Service\Payment\PaymentManager;
use App\Service\Security\PrivacyMasker;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Portal públic vinculat al QR dedicat d'obertura successòria (/herencia/reclamar/{token}).
 * Implementa el flux complet: verificació OTP (6 dígits), càrrega custodiada a Flysystem,
 * taxa d'obertura Stripe, alerta preventiva 72 h al titular i seguiment d'expedient.
 */
class LegacyClaimController extends AbstractController
{
    private const OTP_TTL_SECONDS = 900;
    private const OTP_MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly LegacyCertificateIssuerService $legacyCertificateIssuer,
        private readonly ?SuccessionClaimWorkflowService $workflowService = null,
        private readonly ?SuccessionClaimRepository $claimRepository = null,
        private readonly ?PaymentGatewayInterface $paymentGateway = null,
        private readonly ?PaymentManager $paymentManager = null,
        private readonly ?MailerInterface $mailer = null,
        private readonly string $mailerFromAddress = 'info@registrals.com',
        private readonly string $mailerFromName = 'Registrals',
    ) {
    }

    #[Route('/herencia/reclamar/{token}', name: 'app_legacy_claim_entry', methods: ['GET'])]
    public function show(string $token, ?Request $request = null): Response
    {
        $resolution = $this->legacyCertificateIssuer->resolveClaimByRawToken($token);
        $certificate = $resolution['certificate'];
        $state = $resolution['state'];

        if ($state === 'invalid' || $certificate === null) {
            return $this->render('legacy/claim_entry.html.twig', [
                'token' => $token,
                'state' => 'invalid',
                'certificate' => null,
                'maskedOwnerName' => null,
                'maskedContactName' => null,
                'openingFeeCents' => null,
                'isCurrentSnapshot' => false,
                'itemsCount' => 0,
                'otpState' => null,
                'activeClaim' => null,
                'prefillName' => '',
                'prefillEmail' => '',
            ], new Response('', Response::HTTP_NOT_FOUND));
        }

        $owner = $certificate->getIssuedBy();
        $contact = $certificate->getLegacyContact();

        $sessionKeyPrefix = hash('sha256', $token);
        $otpState = null;
        $activeClaim = null;
        if ($request !== null && $request->hasSession()) {
            $session = $request->getSession();
            $rawOtp = $session->get('legacy_claim_otp_' . $sessionKeyPrefix);
            if (is_array($rawOtp)) {
                $otpState = $rawOtp;
            }
            $claimUuid = $session->get('legacy_claim_uuid_' . $sessionKeyPrefix);
            if (is_string($claimUuid) && $claimUuid !== '' && $this->claimRepository !== null) {
                $loadedClaim = $this->claimRepository->findByUuid($claimUuid);
                if ($loadedClaim instanceof SuccessionClaim && $loadedClaim->getSourceCertificate()->getId() === $certificate->getId()) {
                    $activeClaim = $loadedClaim;
                }
            }
        }

        $prefillName = '';
        $prefillEmail = '';
        $user = $this->container->has('security.token_storage') ? $this->getUser() : null;
        if ($user instanceof \App\Entity\User) {
            $prefillName = $user->getFullName();
            $prefillEmail = $user->getEmail();
        }

        $statusCode = in_array($state, ['revoked', 'superseded'], true)
            ? Response::HTTP_GONE
            : Response::HTTP_OK;

        return $this->render('legacy/claim_entry.html.twig', [
            'token' => $token,
            'state' => $state,
            'certificate' => $certificate,
            'maskedOwnerName' => PrivacyMasker::maskName($owner->getFullName()),
            'maskedContactName' => $contact !== null ? PrivacyMasker::maskName($contact->getFullName()) : null,
            'openingFeeCents' => $owner->getPlan()?->getLegacyClaimOpeningFeeCents() ?? 1900,
            'isCurrentSnapshot' => $resolution['isCurrentSnapshot'],
            'itemsCount' => count($certificate->getIncludedItemCuisSnapshot() ?? []),
            'otpState' => $otpState,
            'activeClaim' => $activeClaim,
            'prefillName' => $prefillName,
            'prefillEmail' => $prefillEmail,
        ], new Response('', $statusCode));
    }

    #[Route('/herencia/reclamar/{token}/enviar-otp', name: 'app_legacy_claim_send_otp', methods: ['POST'])]
    public function sendOtp(string $token, Request $request): Response
    {
        $resolution = $this->legacyCertificateIssuer->resolveClaimByRawToken($token);
        if ($resolution['state'] !== 'valid' || $resolution['certificate'] === null) {
            return $this->redirectToRoute('app_legacy_claim_entry', ['token' => $token]);
        }

        if (!$this->isCsrfTokenValid('legacy_claim_otp_' . $token, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'El testimoni de seguretat no és vàlid. Torna-ho a provar.');

            return $this->redirectToRoute('app_legacy_claim_entry', ['token' => $token]);
        }

        $name = trim((string) $request->request->get('claimant_name', ''));
        $email = mb_strtolower(trim((string) $request->request->get('claimant_email', '')));
        $taxId = strtoupper(trim((string) $request->request->get('claimant_tax_id', '')));

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($taxId) < 5) {
            $this->addFlash('error', 'Introdueix el teu nom complet, un correu electrònic vàlid i el NIF/NIE o passaport (mínim 5 caràcters).');

            return $this->redirectToRoute('app_legacy_claim_entry', ['token' => $token]);
        }

        $otpCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = time() + self::OTP_TTL_SECONDS;
        $sessionKeyPrefix = hash('sha256', $token);

        $request->getSession()->set('legacy_claim_otp_' . $sessionKeyPrefix, [
            'claimantName' => $name,
            'claimantEmail' => $email,
            'maskedEmail' => PrivacyMasker::maskEmail($email),
            'claimantTaxId' => $taxId,
            'otpHash' => hash('sha256', $otpCode),
            'expiresAt' => $expiresAt,
            'attempts' => 0,
            'verifiedAt' => null,
        ]);

        if ($this->mailer !== null) {
            try {
                $emailMessage = (new TemplatedEmail())
                    ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
                    ->to(new Address($email, $name))
                    ->subject(sprintf('Codi de verificació (%s) · Expedient Successori — Registrals', $otpCode))
                    ->htmlTemplate('emails/legacy_claim_otp.html.twig')
                    ->textTemplate('emails/legacy_claim_otp.txt.twig')
                    ->context([
                        'claimantName' => $name,
                        'otpCode' => $otpCode,
                        'certificateNumber' => $resolution['certificate']->getCertificateNumber(),
                    ]);
                $this->mailer->send($emailMessage);
            } catch (\Throwable) {
                $this->addFlash('error', 'No s\'ha pogut enviar el codi de verificació per correu electrònic.');

                return $this->redirectToRoute('app_legacy_claim_entry', ['token' => $token]);
            }
        }

        $this->addFlash('success', sprintf(
            'Hem enviat un codi OTP de 6 dígits a %s. Introdueix-lo tot seguit per habilitar la càrrega documental.',
            PrivacyMasker::maskEmail($email)
        ));

        return $this->redirectToRoute('app_legacy_claim_entry', ['token' => $token]);
    }

    #[Route('/herencia/reclamar/{token}/verificar-otp', name: 'app_legacy_claim_verify_otp', methods: ['POST'])]
    public function verifyOtp(string $token, Request $request): Response
    {
        $resolution = $this->legacyCertificateIssuer->resolveClaimByRawToken($token);
        if ($resolution['state'] !== 'valid' || $resolution['certificate'] === null) {
            return $this->redirectToRoute('app_legacy_claim_entry', ['token' => $token]);
        }

        if (!$this->isCsrfTokenValid('legacy_claim_verify_otp_' . $token, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'El testimoni de seguretat no és vàlid.');

            return $this->redirectToRoute('app_legacy_claim_entry', ['token' => $token]);
        }

        $sessionKeyPrefix = hash('sha256', $token);
        $otpState = $request->getSession()->get('legacy_claim_otp_' . $sessionKeyPrefix);
        if (!is_array($otpState) || !isset($otpState['otpHash'], $otpState['expiresAt'])) {
            $this->addFlash('error', 'Primer has de sol·licitar el codi OTP indicant el teu correu electrònic.');

            return $this->redirectToRoute('app_legacy_claim_entry', ['token' => $token]);
        }

        if (time() > (int) $otpState['expiresAt']) {
            $request->getSession()->remove('legacy_claim_otp_' . $sessionKeyPrefix);
            $this->addFlash('error', 'El codi OTP ha caducat. Sol·licita\'n un de nou.');

            return $this->redirectToRoute('app_legacy_claim_entry', ['token' => $token]);
        }

        $code = preg_replace('/\s+/', '', (string) $request->request->get('otp_code', ''));
        if ($code === null || strlen($code) !== 6 || !hash_equals((string) $otpState['otpHash'], hash('sha256', $code))) {
            $attempts = ((int) ($otpState['attempts'] ?? 0)) + 1;
            if ($attempts >= self::OTP_MAX_ATTEMPTS) {
                $request->getSession()->remove('legacy_claim_otp_' . $sessionKeyPrefix);
                $this->addFlash('error', 'Has superat el màxim d\'intents. Sol·licita un nou codi de verificació.');
            } else {
                $otpState['attempts'] = $attempts;
                $request->getSession()->set('legacy_claim_otp_' . $sessionKeyPrefix, $otpState);
                $this->addFlash('error', sprintf('Codi OTP incorrecte (intent %d de %d).', $attempts, self::OTP_MAX_ATTEMPTS));
            }

            return $this->redirectToRoute('app_legacy_claim_entry', ['token' => $token]);
        }

        $otpState['verifiedAt'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        unset($otpState['otpHash']);
        $request->getSession()->set('legacy_claim_otp_' . $sessionKeyPrefix, $otpState);

        $this->addFlash('success', 'Identitat verificada correctament. Ja pots adjuntar la documentació oficial acreditativa.');

        return $this->redirectToRoute('app_legacy_claim_entry', ['token' => $token]);
    }

    #[Route('/herencia/reclamar/{token}/reiniciar', name: 'app_legacy_claim_reset', methods: ['POST'])]
    public function resetFlow(string $token, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('legacy_claim_reset_' . $token, (string) $request->request->get('_token', ''))) {
            return $this->redirectToRoute('app_legacy_claim_entry', ['token' => $token]);
        }

        $sessionKeyPrefix = hash('sha256', $token);
        $request->getSession()->remove('legacy_claim_otp_' . $sessionKeyPrefix);
        $request->getSession()->remove('legacy_claim_uuid_' . $sessionKeyPrefix);

        return $this->redirectToRoute('app_legacy_claim_entry', ['token' => $token]);
    }

    #[Route('/herencia/reclamar/{token}/pujar-documents', name: 'app_legacy_claim_upload', methods: ['POST'])]
    public function uploadDocuments(string $token, Request $request): Response
    {
        $resolution = $this->legacyCertificateIssuer->resolveClaimByRawToken($token);
        $certificate = $resolution['certificate'];
        if ($resolution['state'] !== 'valid' || $certificate === null) {
            return $this->redirectToRoute('app_legacy_claim_entry', ['token' => $token]);
        }

        if (!$this->isCsrfTokenValid('legacy_claim_upload_' . $token, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'El testimoni de seguretat no és vàlid.');

            return $this->redirectToRoute('app_legacy_claim_entry', ['token' => $token]);
        }

        if ($this->workflowService === null) {
            $this->addFlash('error', 'El servei de tramitació successòria no està disponible.');

            return $this->redirectToRoute('app_legacy_claim_entry', ['token' => $token]);
        }

        $sessionKeyPrefix = hash('sha256', $token);
        $otpState = $request->getSession()->get('legacy_claim_otp_' . $sessionKeyPrefix);
        if (!is_array($otpState) || empty($otpState['verifiedAt'])) {
            $this->addFlash('error', 'Cal verificar primer el correu electrònic amb el codi OTP.');

            return $this->redirectToRoute('app_legacy_claim_entry', ['token' => $token]);
        }

        $deathFile = $request->files->get('death_certificate');
        $titleFile = $request->files->get('succession_title');
        if (!$deathFile instanceof UploadedFile || !$titleFile instanceof UploadedFile) {
            $this->addFlash('error', 'Cal adjuntar obligatòriament tant el Certificat Oficial de Defunció com el Títol Successori.');

            return $this->redirectToRoute('app_legacy_claim_entry', ['token' => $token]);
        }

        try {
            $verifiedAt = new \DateTimeImmutable((string) $otpState['verifiedAt']);
            $claim = $this->workflowService->submitDocumentsAndCreateClaim(
                sourceCertificate: $certificate,
                claimantName: (string) $otpState['claimantName'],
                claimantEmail: (string) $otpState['claimantEmail'],
                claimantTaxId: (string) $otpState['claimantTaxId'],
                otpVerifiedAt: $verifiedAt,
                deathCertificateFile: $deathFile,
                successionTitleFile: $titleFile,
                clientIp: $request->getClientIp(),
            );

            $request->getSession()->set('legacy_claim_uuid_' . $sessionKeyPrefix, $claim->getUuid()->toRfc4122());
            $this->addFlash('success', sprintf(
                'Expedient %s registrat i documentació custodiada sota xifratge SHA-256. Completa la taxa d\'obertura per activar la revisió.',
                $claim->getClaimReference()
            ));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_legacy_claim_entry', ['token' => $token]);
    }

    #[Route('/herencia/reclamar/{token}/pagar', name: 'app_legacy_claim_checkout', methods: ['POST'])]
    public function checkoutOpeningFee(string $token, Request $request): Response
    {
        $resolution = $this->legacyCertificateIssuer->resolveClaimByRawToken($token);
        $certificate = $resolution['certificate'];
        if ($resolution['state'] !== 'valid' || $certificate === null) {
            return $this->redirectToRoute('app_legacy_claim_entry', ['token' => $token]);
        }

        if (!$this->isCsrfTokenValid('legacy_claim_pay_' . $token, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'El testimoni de seguretat no és vàlid.');

            return $this->redirectToRoute('app_legacy_claim_entry', ['token' => $token]);
        }

        $sessionKeyPrefix = hash('sha256', $token);
        $claimUuid = (string) ($request->request->get('claim_uuid') ?: $request->getSession()->get('legacy_claim_uuid_' . $sessionKeyPrefix, ''));
        $claim = ($claimUuid !== '' && $this->claimRepository !== null)
            ? $this->claimRepository->findByUuid($claimUuid)
            : null;

        if (!$claim instanceof SuccessionClaim || $claim->getSourceCertificate()->getId() !== $certificate->getId()) {
            $this->addFlash('error', 'No s\'ha localitzat l\'expedient documental per abonar.');

            return $this->redirectToRoute('app_legacy_claim_entry', ['token' => $token]);
        }

        if ($claim->getStatus()->isResolved() || $claim->getOpeningFeePaidCents() > 0) {
            return $this->redirectToRoute('app_legacy_claim_entry', ['token' => $token]);
        }

        if ($this->paymentGateway === null || $this->paymentManager === null) {
            $this->addFlash('error', 'La passarel·la de pagament no es troba disponible.');

            return $this->redirectToRoute('app_legacy_claim_entry', ['token' => $token]);
        }

        $amountCents = $claim->getDeceasedOwner()->getPlan()?->getLegacyClaimOpeningFeeCents() ?? 1900;
        $successUrl = $this->generateUrl('app_legacy_claim_payment_return', [
            'token' => $token,
            'claim' => $claim->getUuid()->toRfc4122(),
            'session_id' => '{CHECKOUT_SESSION_ID}',
        ], UrlGeneratorInterface::ABSOLUTE_URL);
        $cancelUrl = $this->generateUrl('app_legacy_claim_entry', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

        try {
            $checkout = $this->paymentGateway->createSuccessionClaimCheckout($claim, $amountCents, $successUrl, $cancelUrl);
            $payerUser = $claim->getClaimantUser() ?? $claim->getDeceasedOwner();

            $this->paymentManager->createPendingTransaction(
                $payerUser,
                PaymentType::SUCCESSION_CLAIM,
                $amountCents,
                $checkout['sessionId'],
                [
                    'type' => 'succession_claim',
                    'claim_id' => $claim->getId(),
                    'claim_uuid' => $claim->getUuid()->toRfc4122(),
                    'claimant_email' => $claim->getClaimantEmail(),
                ]
            );

            if (str_contains($checkout['url'], 'mock=1')) {
                $this->paymentManager->processWebhook([
                    'id' => 'evt_mock_claim_' . bin2hex(random_bytes(8)),
                    'type' => 'checkout.session.completed',
                    'data' => [
                        'object' => [
                            'id' => $checkout['sessionId'],
                            'client_reference_id' => $claim->getUuid()->toRfc4122(),
                            'amount_total' => $amountCents,
                            'payment_intent' => 'pi_mock_' . bin2hex(random_bytes(8)),
                            'metadata' => [
                                'type' => 'succession_claim',
                                'user_id' => (string) $payerUser->getId(),
                                'claim_id' => (string) $claim->getId(),
                            ],
                        ],
                    ],
                ]);
            }

            return $this->redirect($checkout['url']);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Error en iniciar el pagament de la taxa d\'obertura: ' . $e->getMessage());

            return $this->redirectToRoute('app_legacy_claim_entry', ['token' => $token]);
        }
    }

    #[Route('/herencia/reclamar/{token}/retorn', name: 'app_legacy_claim_payment_return', methods: ['GET'])]
    public function paymentReturn(string $token, Request $request): Response
    {
        $claimUuid = (string) $request->query->get('claim', '');
        if ($claimUuid !== '' && $request->hasSession()) {
            $request->getSession()->set('legacy_claim_uuid_' . hash('sha256', $token), $claimUuid);
        }

        $this->addFlash('success', 'Taxa d\'obertura abonada! S\'ha notificat preventivament al titular (finestra de seguretat de 72 h) i iniciat l\'anàlisi documental.');

        return $this->redirectToRoute('app_legacy_claim_entry', ['token' => $token]);
    }
}
