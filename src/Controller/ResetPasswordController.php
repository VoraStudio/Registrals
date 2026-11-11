<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\ResetPasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Service\Security\PasswordResetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ResetPasswordController extends AbstractController
{
    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function request(Request $request, PasswordResetService $passwordResetService): Response
    {
        if ($this->getUser()) {
            $this->addFlash('info', 'Ja tens la sessió iniciada. Pots actualitzar la teva contrasenya des del perfil.');
            return $this->redirectToRoute('app_profile');
        }

        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $passwordResetService->requestReset((string) $form->get('email')->getData());

            $this->addFlash(
                'success',
                'Si aquesta adreça està associada a un compte actiu, rebràs un correu amb les instruccions en uns instants.'
            );

            return $this->redirectToRoute('app_forgot_password', ['sent' => 1]);
        }

        return $this->render('security/forgot_password.html.twig', [
            'requestForm' => $form,
            'emailSent' => $request->query->getBoolean('sent'),
        ]);
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function reset(string $token, Request $request, PasswordResetService $passwordResetService): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_profile');
        }

        $user = $passwordResetService->findValidUserByRawToken($token);
        if ($user === null) {
            $this->addFlash('error', 'Aquest enllaç de recuperació no és vàlid o ha caducat. Sol·licita\'n un de nou.');
            return $this->redirectToRoute('app_forgot_password');
        }

        $form = $this->createForm(ResetPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = (string) $form->get('newPassword')->getData();
            $passwordResetService->completeReset($user, $newPassword);

            $this->addFlash('success', 'Contrasenya restablerta correctament! Ja pots iniciar sessió.');
            return $this->redirectToRoute('app_login');
        }

        $response = $this->render('security/reset_password.html.twig', [
            'resetForm' => $form,
        ]);

        // Evitem filtrar el token secret per capçaleres Referer o caché de navegador
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
