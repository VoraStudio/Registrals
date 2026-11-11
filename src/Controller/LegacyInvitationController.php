<?php

declare(strict_types=1);

namespace App\Controller;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\Entity\LegacyContact;
use App\Entity\User;
use App\Enum\EmailOtpPurpose;
use App\EventSubscriber\LegalGateSubscriber;
use App\Form\LegacyFreeSignupFormType;
use App\Repository\PlanRepository;
use App\Repository\UserRepository;
use App\Service\Legacy\LegacyDesignationService;
use App\Service\Security\AuthChallengeStorage;
use App\Service\Security\EmailOtpService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class LegacyInvitationController extends AbstractController
{
    public function __construct(
        private readonly LegacyDesignationService $legacyService,
        private readonly UserRepository $userRepository,
        private readonly PlanRepository $planRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EmailOtpService $emailOtpService,
        private readonly AuthChallengeStorage $challengeStorage,
        private readonly ?UserActivityLogger $activityLogger = null,
    ) {
    }

    #[Route('/llegat/acceptar/{token}', name: 'app_legacy_invitation_show', methods: ['GET'])]
    public function show(string $token): Response
    {
        $resolved = $this->legacyService->resolveInvitationByRawToken($token);
        if ($resolved['state'] !== 'valid' || !$resolved['contact'] instanceof LegacyContact) {
            return $this->renderUnavailable($resolved['state'], $resolved['contact']);
        }

        $contact = $resolved['contact'];
        $currentUser = $this->getUser();
        $existingAccount = $this->userRepository->findOneBy(['email' => $contact->getEmail()]);

        $signupForm = null;
        if (!$currentUser instanceof User && $existingAccount === null) {
            [$defaultFirstName, $defaultLastName] = $this->splitFullName($contact->getFullName());
            $signupForm = $this->createForm(LegacyFreeSignupFormType::class, [
                'firstName' => $defaultFirstName,
                'lastName' => $defaultLastName,
            ], [
                'action' => $this->generateUrl('app_legacy_invitation_signup', ['token' => $token]),
            ]);
        }

        return $this->renderInvitationResponse($token, $contact, $currentUser instanceof User ? $currentUser : null, $existingAccount !== null, $signupForm);
    }

    #[Route('/llegat/acceptar/{token}/entrar', name: 'app_legacy_invitation_login', methods: ['GET'])]
    public function redirectToLogin(string $token, Request $request): Response
    {
        $resolved = $this->legacyService->resolveInvitationByRawToken($token);
        if ($resolved['state'] !== 'valid' || !$resolved['contact'] instanceof LegacyContact) {
            return $this->renderUnavailable($resolved['state'], $resolved['contact']);
        }

        if ($request->hasSession()) {
            $targetUrl = $this->generateUrl('app_legacy_invitation_show', ['token' => $token]);
            $request->getSession()->set('_security.main.target_path', $targetUrl);
            $request->getSession()->set('pending_legacy_accept_token', $token);
        }

        $this->addFlash('info', 'Inicia sessió amb el compte corresponent per confirmar la designació.');

        return $this->redirectToRoute('app_login');
    }

    #[Route('/llegat/acceptar/{token}/confirmar', name: 'app_legacy_invitation_accept', methods: ['POST'])]
    public function accept(string $token, Request $request): Response
    {
        $resolved = $this->legacyService->resolveInvitationByRawToken($token);
        if ($resolved['state'] !== 'valid' || !$resolved['contact'] instanceof LegacyContact) {
            return $this->renderUnavailable($resolved['state'], $resolved['contact']);
        }

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->redirectToLogin($token, $request);
        }

        if (!$currentUser->hasAcceptedLegalTerms(LegalGateSubscriber::CURRENT_TERMS_VERSION)) {
            if ($request->hasSession()) {
                $request->getSession()->set('pending_legacy_accept_token', $token);
            }
            $this->addFlash('info', 'Abans de confirmar la designació, accepta les clàusules de custòdia i certificació.');

            return $this->redirectToRoute('app_legal_accept_terms');
        }

        $csrfToken = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('legacy_accept_' . $token, $csrfToken)) {
            $this->addFlash('error', 'El testimoni de seguretat no és vàlid. Torna-ho a provar.');

            return $this->redirectToRoute('app_legacy_invitation_show', ['token' => $token]);
        }

        $contact = $resolved['contact'];

        try {
            $this->legacyService->accept($contact, $currentUser, $request->getClientIp());
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_legacy_invitation_show', ['token' => $token]);
        }

        if ($request->hasSession()) {
            $request->getSession()->remove('pending_legacy_accept_token');
        }

        return $this->render('legacy/invitation_completed.html.twig', [
            'outcome' => 'accepted',
            'contact' => $contact,
            'owner' => $contact->getOwner(),
            'isLoggedIn' => true,
        ]);
    }

    #[Route('/llegat/acceptar/{token}/refusar', name: 'app_legacy_invitation_decline', methods: ['POST'])]
    public function decline(string $token, Request $request): Response
    {
        $resolved = $this->legacyService->resolveInvitationByRawToken($token);
        if ($resolved['state'] !== 'valid' || !$resolved['contact'] instanceof LegacyContact) {
            return $this->renderUnavailable($resolved['state'], $resolved['contact']);
        }

        $csrfToken = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('legacy_decline_' . $token, $csrfToken)) {
            $this->addFlash('error', 'El testimoni de seguretat no és vàlid.');

            return $this->redirectToRoute('app_legacy_invitation_show', ['token' => $token]);
        }

        $contact = $resolved['contact'];
        $this->legacyService->decline($contact, $request->getClientIp());

        if ($request->hasSession()) {
            $request->getSession()->remove('pending_legacy_accept_token');
        }

        return $this->render('legacy/invitation_completed.html.twig', [
            'outcome' => 'declined',
            'contact' => $contact,
            'owner' => $contact->getOwner(),
            'isLoggedIn' => $this->getUser() instanceof User,
        ]);
    }

    #[Route('/llegat/acceptar/{token}/registrar', name: 'app_legacy_invitation_signup', methods: ['POST'])]
    public function signup(string $token, Request $request): Response
    {
        if ($this->getUser() instanceof User) {
            return $this->redirectToRoute('app_legacy_invitation_show', ['token' => $token]);
        }

        $resolved = $this->legacyService->resolveInvitationByRawToken($token);
        if ($resolved['state'] !== 'valid' || !$resolved['contact'] instanceof LegacyContact) {
            return $this->renderUnavailable($resolved['state'], $resolved['contact']);
        }

        $contact = $resolved['contact'];
        $existingAccount = $this->userRepository->findOneBy(['email' => $contact->getEmail()]);
        if ($existingAccount instanceof User) {
            $this->addFlash('info', 'Aquesta adreça ja té un compte a Registrals. Inicia sessió per acceptar.');

            return $this->redirectToRoute('app_legacy_invitation_login', ['token' => $token]);
        }

        $form = $this->createForm(LegacyFreeSignupFormType::class, null, [
            'action' => $this->generateUrl('app_legacy_invitation_signup', ['token' => $token]),
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderInvitationResponse($token, $contact, null, false, $form);
        }

        $freePlan = $this->planRepository->findDefaultFreePlan();
        if ($freePlan === null) {
            throw new \RuntimeException('El pla Free per defecte no està configurat a la base de dades.');
        }

        $newUser = (new User())
            ->setEmail($contact->getEmail())
            ->setFirstName(trim((string) $form->get('firstName')->getData()))
            ->setLastName(trim((string) $form->get('lastName')->getData()))
            ->setPlan($freePlan)
            ->setRegistrationIp($request->getClientIp());

        $plainPassword = (string) $form->get('plainPassword')->getData();
        $newUser->setPassword($this->passwordHasher->hashPassword($newUser, $plainPassword));

        $this->entityManager->persist($newUser);
        $this->entityManager->flush();

        $this->activityLogger?->log(
            user: $newUser,
            action: UserActivityAction::REGISTER,
            description: sprintf('Alta gratuïta al Pla Free des d\'invitació de persona de confiança (%s)', $newUser->getEmail()),
            details: [
                'source' => 'legacy_invitation',
                'legacy_contact_uuid' => $contact->getUuid()->toRfc4122(),
                'ip' => $request->getClientIp(),
            ]
        );

        $otpResult = $this->emailOtpService->issueOtp($newUser, EmailOtpPurpose::REGISTRATION_VERIFY);
        $userId = $newUser->getId();
        if ($userId !== null && $request->hasSession()) {
            $session = $request->getSession();
            $this->challengeStorage->store($session, $userId, EmailOtpPurpose::REGISTRATION_VERIFY, false);
            $session->set('pending_legacy_accept_token', $token);
            $session->remove('intended_plan');
        }

        if ($otpResult['error'] !== null) {
            $this->addFlash('error', $otpResult['error']);
        } else {
            $this->addFlash('info', 'T\'hem enviat un codi de 6 dígits al teu correu per activar el Pla Free i continuar.');
        }

        return $this->redirectToRoute('app_register_verify_email');
    }

    private function renderInvitationResponse(
        string $token,
        LegacyContact $contact,
        ?User $currentUser,
        bool $accountExistsForContact,
        ?\Symfony\Component\Form\FormInterface $signupForm
    ): Response {
        $isEmailMatch = $currentUser instanceof User
            && mb_strtolower(trim($currentUser->getEmail())) === $contact->getEmail();

        $response = $this->render('legacy/invitation.html.twig', [
            'token' => $token,
            'contact' => $contact,
            'owner' => $contact->getOwner(),
            'currentUser' => $currentUser,
            'isEmailMatch' => $isEmailMatch,
            'accountExistsForContact' => $accountExistsForContact,
            'signupForm' => $signupForm?->createView(),
        ]);

        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    private function renderUnavailable(string $reason, ?LegacyContact $contact): Response
    {
        return $this->render('legacy/invitation_unavailable.html.twig', [
            'reason' => $reason,
            'contact' => $contact,
        ], new Response('', $reason === 'invalid' ? Response::HTTP_NOT_FOUND : Response::HTTP_OK));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitFullName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2);
        if ($parts === false) {
            return ['', ''];
        }

        return [
            $parts[0] ?? '',
            $parts[1] ?? '',
        ];
    }
}
