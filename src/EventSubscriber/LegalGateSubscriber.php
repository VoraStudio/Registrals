<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Intercepta qualsevol petició a l'àrea privada (/app/*) i comprova
 * que l'usuari hagi acceptat explícitament les condicions i clàusules de custòdia i certificació digital vigents.
 * Si no les té acceptades (ex. registre OAuth2 o canvi de versió de termes),
 * el redirigeix obligatòriament a la pantalla de lectura i confirmació solemne.
 */
class LegalGateSubscriber implements EventSubscriberInterface
{
    public const CURRENT_TERMS_VERSION = 'v1.0';
    public const GATE_ROUTE = 'app_legal_accept_terms';

    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Prioritat 0 per executar-se un cop inicialitzat el tallafoc de seguretat
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Només s'aplica a rutes de l'àrea privada (/app/*)
        if (!str_starts_with($path, '/app')) {
            return;
        }

        $route = (string) $request->attributes->get('_route', '');

        // Rutes exemptes: la pròpia pantalla d'acceptació i el tancament de sessió
        if ($route === self::GATE_ROUTE || $route === 'app_logout') {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        // Si l'usuari no té acceptada la versió vigent, redirigim al Legal Gate
        if (!$user->hasAcceptedLegalTerms(self::CURRENT_TERMS_VERSION)) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate(self::GATE_ROUTE)));
        }
    }
}
