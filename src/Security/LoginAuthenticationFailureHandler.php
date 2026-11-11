<?php

declare(strict_types=1);

namespace App\Security;

use App\Enum\EmailOtpPurpose;
use App\Security\Exception\PendingEmailVerificationException;
use App\Security\Exception\PendingTwoFactorAuthenticationException;
use App\Service\Security\AuthChallengeStorage;
use App\Service\Security\EmailOtpService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationFailureHandler;
use Symfony\Component\Security\Http\HttpUtils;

/**
 * Gestiona els reptes pendents (verificació de correu i 2FA) quan s'inicia sessió per contrasenya,
 * delegant la resta d'errors d'autenticació al comportament estàndard de Symfony.
 */
class LoginAuthenticationFailureHandler extends DefaultAuthenticationFailureHandler
{
    public function __construct(
        HttpKernelInterface $httpKernel,
        HttpUtils $httpUtils,
        private readonly EmailOtpService $emailOtpService,
        private readonly AuthChallengeStorage $challengeStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($httpKernel, $httpUtils, [], $logger);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof PendingEmailVerificationException) {
            return $this->startChallenge(
                $request,
                $exception->getUser(),
                EmailOtpPurpose::REGISTRATION_VERIFY,
                respectCooldown: true,
                targetRoute: 'app_register_verify_email',
                flashMessage: 'El teu compte està pendent de verificar el correu electrònic. Introdueix el codi de 6 dígits que t\'hem enviat.'
            );
        }

        if ($exception instanceof PendingTwoFactorAuthenticationException) {
            return $this->startChallenge(
                $request,
                $exception->getUser(),
                EmailOtpPurpose::LOGIN_2FA,
                respectCooldown: false,
                targetRoute: 'app_login_2fa',
                flashMessage: 'Hem enviat un codi de verificació en dos passos (6 dígits) al teu correu electrònic.'
            );
        }

        return parent::onAuthenticationFailure($request, $exception);
    }

    private function startChallenge(
        Request $request,
        \App\Entity\User $user,
        EmailOtpPurpose $purpose,
        bool $respectCooldown,
        string $targetRoute,
        string $flashMessage
    ): RedirectResponse {
        $result = $this->emailOtpService->issueOtp($user, $purpose, respectCooldown: $respectCooldown);
        $userId = $user->getId();
        if ($userId !== null && $request->hasSession()) {
            $rememberMe = $request->request->getBoolean('_remember_me');
            $this->challengeStorage->store($request->getSession(), $userId, $purpose, $rememberMe);

            $flashBag = $request->getSession()->getFlashBag();
            if ($result['error'] !== null) {
                $flashBag->add('error', $result['error']);
            } else {
                $flashBag->add('info', $flashMessage);
            }
        }

        return new RedirectResponse($this->urlGenerator->generate($targetRoute));
    }
}

