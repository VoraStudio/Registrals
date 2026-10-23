<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, \Symfony\Component\HttpFoundation\Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        $recognizedUser = null;
        if ($cookie = $request->cookies->get('reg_device_user')) {
            $decoded = json_decode((string) base64_decode((string) $cookie, true), true);
            if (is_array($decoded) && !empty($decoded['email'])) {
                $recognizedUser = $decoded;
                if (!$lastUsername) {
                    $lastUsername = (string) $decoded['email'];
                }
            }
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'recognized_user' => $recognizedUser,
            'error' => $error,
        ]);
    }

    #[Route(path: '/login/forget-device', name: 'app_login_forget_device', methods: ['GET', 'POST'])]
    public function forgetDevice(): Response
    {
        $response = $this->redirectToRoute('app_login');
        $response->headers->clearCookie(\App\EventSubscriber\DeviceRecognitionSubscriber::COOKIE_NAME, '/');

        return $response;
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
