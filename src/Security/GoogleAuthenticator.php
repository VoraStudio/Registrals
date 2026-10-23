<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\PlanRepository;
use App\Repository\UserRepository;
use App\Service\OAuth\GoogleOAuthService;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class GoogleAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly GoogleOAuthService $googleOAuth,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly PlanRepository $planRepository,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $session = $request->getSession();
        $state = $request->query->get('state');

        if (!$state || $state !== $session->get('oauth2state')) {
            $session->remove('oauth2state');
            throw new AuthenticationException('Estat d\'autenticació no vàlid o sessió expirada.');
        }

        $session->remove('oauth2state');
        $code = (string) $request->query->get('code');

        if ($code === '') {
            throw new AuthenticationException('Codi d\'autorització de Google no rebut.');
        }

        $provider = $this->googleOAuth->getClient();

        try {
            $accessToken = $provider->getAccessToken('authorization_code', [
                'code' => $code,
            ]);

            /** @var GoogleUser $googleUser */
            $googleUser = $provider->getResourceOwner($accessToken);
        } catch (\Throwable $e) {
            throw new AuthenticationException('Error comunicant amb Google: ' . $e->getMessage());
        }

        $email = $googleUser->getEmail();
        if (!$email) {
            throw new AuthenticationException('No s\'ha pogut obtenir el correu electrònic des de Google.');
        }

        return new SelfValidatingPassport(
            new UserBadge($email, function () use ($googleUser, $email): User {
                /** @var User|null $existingUser */
                $existingUser = $this->userRepository->findOneBy(['email' => $email]);

                if ($existingUser) {
                    if ($existingUser->getAuthProvider() === 'local' && !$existingUser->getAuthProviderId()) {
                        $existingUser->setAuthProvider('google');
                        $existingUser->setAuthProviderId($googleUser->getId());
                        $this->entityManager->flush();
                    }
                    return $existingUser;
                }

                $freePlan = $this->planRepository->findDefaultFreePlan();
                if ($freePlan === null) {
                    throw new \RuntimeException('El pla Free no està inicialitzat a la base de dades.');
                }

                $user = new User();
                $user->setEmail($email);
                $user->setFirstName($googleUser->getFirstName() ?? 'Usuari');
                $user->setLastName($googleUser->getLastName() ?? 'Google');
                $user->setAuthProvider('google');
                $user->setAuthProviderId($googleUser->getId());
                $user->setPlan($freePlan);

                $this->entityManager->persist($user);
                $this->entityManager->flush();

                return $user;
            }),
            [
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();
        if ($user instanceof User && !$user->isOnboardingCompleted()) {
            return new RedirectResponse($this->urlGenerator->generate('app_onboarding_welcome'));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->getFlashBag()->add('error', 'Error en iniciar sessió amb Google: ' . $exception->getMessageKey());

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}
