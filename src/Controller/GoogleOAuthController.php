<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\OAuth\GoogleOAuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GoogleOAuthController extends AbstractController
{
    #[Route('/connect/google', name: 'connect_google_start')]
    public function connect(Request $request, GoogleOAuthService $googleOAuth): RedirectResponse
    {
        $provider = $googleOAuth->getClient();
        $authUrl = $provider->getAuthorizationUrl([
            'scope' => ['email', 'profile'],
        ]);

        $request->getSession()->set('oauth2state', $provider->getState());

        return $this->redirect($authUrl);
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectCheck(): Response
    {
        // This will be intercepted by GoogleAuthenticator in the security firewall
        return new Response();
    }
}
