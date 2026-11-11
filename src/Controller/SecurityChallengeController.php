<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\EmailOtpPurpose;
use App\Enum\EmailOtpVerificationStatus;
use App\Form\OtpVerificationFormType;
use App\Repository\UserRepository;
use App\Service\Security\AuthChallengeStorage;
use App\Service\Security\EmailOtpService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;

class SecurityChallengeController extends AbstractController
{
    public function __construct(
        private readonly EmailOtpService $emailOtpService,
        private readonly AuthChallengeStorage $challengeStorage,
        private readonly UserRepository $userRepository,
        private readonly Security $security,
    ) {
    }

    #[Route('/register/verify-email', name: 'app_register_verify_email', methods: ['GET', 'POST'])]
    public function verifyRegistrationEmail(Request $request): Response
    {
        return $this->handleChallenge(
            $request,
            EmailOtpPurpose::REGISTRATION_VERIFY,
            'app_register_verify_email',
            'app_register_verify_email_resend',
            'app_register_verify_cancel'
        );
    }

    #[Route('/register/verify-email/resend', name: 'app_register_verify_email_resend', methods: ['POST'])]
    public function resendRegistrationEmail(Request $request): Response
    {
        return $this->handleResend(
            $request,
            EmailOtpPurpose::REGISTRATION_VERIFY,
            'app_register_verify_email'
        );
    }

    #[Route('/register/verify-email/cancel', name: 'app_register_verify_cancel', methods: ['GET'])]
    public function cancelRegistrationVerify(Request $request): Response
    {
        return $this->cancelChallenge($request);
    }

    #[Route('/login/2fa', name: 'app_login_2fa', methods: ['GET', 'POST'])]
    public function verifyLoginTwoFactor(Request $request): Response
    {
        return $this->handleChallenge(
            $request,
            EmailOtpPurpose::LOGIN_2FA,
            'app_login_2fa',
            'app_login_2fa_resend',
            'app_login_2fa_cancel'
        );
    }

    #[Route('/login/2fa/resend', name: 'app_login_2fa_resend', methods: ['POST'])]
    public function resendLoginTwoFactor(Request $request): Response
    {
        return $this->handleResend(
            $request,
            EmailOtpPurpose::LOGIN_2FA,
            'app_login_2fa'
        );
    }

    #[Route('/login/2fa/cancel', name: 'app_login_2fa_cancel', methods: ['GET'])]
    public function cancelLoginTwoFactor(Request $request): Response
    {
        return $this->cancelChallenge($request);
    }

    private function handleChallenge(
        Request $request,
        EmailOtpPurpose $purpose,
        string $formRoute,
        string $resendRoute,
        string $cancelRoute
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $context = $this->resolveChallengeContext($request, $purpose);
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        /** @var User $user */
        $user = $context['user'];
        $rememberMe = $context['remember_me'];

        $form = $this->createForm(OtpVerificationFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $code = (string) $form->get('code')->getData();
            $status = $this->emailOtpService->verifyOtp($user, $purpose, $code);

            if ($status === EmailOtpVerificationStatus::VALID) {
                $this->challengeStorage->clear($request->getSession());
                $badges = $rememberMe ? [(new RememberMeBadge())->enable()] : [];
                $authResponse = $this->security->login($user, 'form_login', 'main', $badges);

                if ($purpose === EmailOtpPurpose::REGISTRATION_VERIFY) {
                    $this->addFlash('success', 'Correu electrònic verificat correctament! Revisa i accepta les clàusules de custòdia per accedir-hi.');
                    if ($request->hasSession() && $request->getSession()->has('pending_legacy_accept_token')) {
                        return $this->redirectToRoute('app_legal_accept_terms');
                    }
                    if ($request->getSession()->get('intended_plan') === 'plus') {
                        return $this->redirectToRoute('app_plan');
                    }

                    return $this->redirectToRoute('app_dashboard');
                }

                $this->addFlash('success', 'Verificació en dos passos completada correctament.');

                return $authResponse ?? $this->redirectToRoute('app_dashboard');
            }

            $errorMessage = match ($status) {
                EmailOtpVerificationStatus::EXPIRED_OR_MISSING => 'Aquest codi ha caducat o no està actiu. Sol·licita\'n un de nou amb el botó inferior.',
                EmailOtpVerificationStatus::MAX_ATTEMPTS_EXCEEDED => 'Has superat el límit de 5 intents fallits. Per seguretat, hem invalidat aquest codi; sol·licita\'n un de nou.',
                default => 'El codi de 6 dígits no és correcte. Comprova el correu electrònic i torna-ho a intentar.',
            };
            $form->get('code')->addError(new FormError($errorMessage));
        }

        $response = $this->render('security/otp_challenge.html.twig', [
            'otpForm' => $form,
            'purpose' => $purpose,
            'maskedEmail' => $this->emailOtpService->maskEmail($user->getEmail()),
            'cooldownRemaining' => $this->emailOtpService->getResendCooldownRemaining($user, $purpose),
            'formRoute' => $formRoute,
            'resendRoute' => $resendRoute,
            'cancelRoute' => $cancelRoute,
            'csrfResendTokenId' => 'otp_resend_' . $purpose->value,
        ]);

        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    private function handleResend(Request $request, EmailOtpPurpose $purpose, string $challengeRoute): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $context = $this->resolveChallengeContext($request, $purpose);
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('otp_resend_' . $purpose->value, $token)) {
            $this->addFlash('error', 'El testimoni de seguretat del reenviament no és vàlid. Torna-ho a provar.');
            return $this->redirectToRoute($challengeRoute);
        }

        /** @var User $user */
        $user = $context['user'];
        $result = $this->emailOtpService->issueOtp($user, $purpose, respectCooldown: true);

        if ($result['error'] !== null) {
            $this->addFlash('error', $result['error']);
        } elseif ($result['sent']) {
            $this->addFlash('success', 'Hem enviat un nou codi de 6 dígits al teu correu electrònic.');
        } elseif ($result['cooldownRemaining'] > 0) {
            $this->addFlash('info', sprintf(
                'Espera %d segons abans de demanar un altre codi de verificació.',
                $result['cooldownRemaining']
            ));
        }

        return $this->redirectToRoute($challengeRoute);
    }

    private function cancelChallenge(Request $request): Response
    {
        if ($request->hasSession()) {
            $this->challengeStorage->clear($request->getSession());
        }

        return $this->redirectToRoute('app_login');
    }

    /**
     * @return array{user: User, remember_me: bool}|RedirectResponse
     */
    private function resolveChallengeContext(Request $request, EmailOtpPurpose $purpose): array|RedirectResponse
    {
        if (!$request->hasSession()) {
            return $this->redirectToRoute('app_login');
        }

        $session = $request->getSession();
        $challenge = $this->challengeStorage->get($session, $purpose);
        if ($challenge === null) {
            $this->addFlash('warning', 'La sessió de verificació ha caducat o no està disponible. Inicia sessió de nou.');
            return $this->redirectToRoute('app_login');
        }

        $user = $this->userRepository->find($challenge['user_id']);
        if (!$user instanceof User || !$user->isActive() || $user->getAuthProvider() !== 'local') {
            $this->challengeStorage->clear($session);
            $this->addFlash('error', 'El compte no està disponible per completar aquesta verificació.');
            return $this->redirectToRoute('app_login');
        }

        if ($purpose === EmailOtpPurpose::REGISTRATION_VERIFY && $user->isEmailVerified()) {
            $this->challengeStorage->clear($session);
            $this->addFlash('info', 'El teu correu electrònic ja estava verificat. Pots iniciar sessió directament.');
            return $this->redirectToRoute('app_login');
        }

        if ($purpose === EmailOtpPurpose::LOGIN_2FA && !$user->requiresLoginTwoFactor()) {
            $this->challengeStorage->clear($session);
            return $this->redirectToRoute('app_login');
        }

        return [
            'user' => $user,
            'remember_me' => $challenge['remember_me'],
        ];
    }
}

