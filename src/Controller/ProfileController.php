<?php

declare(strict_types=1);

namespace App\Controller;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\ProfileFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/app/perfil', name: 'app_profile', methods: ['GET', 'POST'])]
    #[Route('/app/profile', name: 'app_profile_legacy', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // Formulari de dades personals
        $profileForm = $this->createForm(ProfileFormType::class, $user);
        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Les dades del teu perfil s\'han actualitzat correctament.');
            return $this->redirectToRoute('app_profile');
        }

        // Formulari de contrasenya (només si és compte local)
        $passwordForm = null;
        $isOAuthUser = $user->getAuthProvider() !== 'local';

        if (!$isOAuthUser) {
            $passwordForm = $this->createForm(ChangePasswordFormType::class);
            $passwordForm->handleRequest($request);

            if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
                $newPassword = (string) $passwordForm->get('newPassword')->getData();
                $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hashedPassword);
                $user->setUpdatedAt(new \DateTimeImmutable());
                $entityManager->flush();

                $this->addFlash('success', 'La contrasenya de seguretat s\'ha modificat correctament.');
                return $this->redirectToRoute('app_profile');
            }
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'profileForm' => $profileForm->createView(),
            'passwordForm' => $passwordForm?->createView(),
            'isOAuthUser' => $isOAuthUser,
        ]);
    }

    #[Route('/app/perfil/seguretat/2fa', name: 'app_profile_toggle_2fa', methods: ['POST'])]
    public function toggleTwoFactor(
        Request $request,
        EntityManagerInterface $entityManager,
        ?UserActivityLogger $activityLogger = null
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->getAuthProvider() !== 'local') {
            $this->addFlash('error', 'Els comptes vinculats a Google gestionen la verificació en dos passos directament amb Google.');
            return $this->redirectToRoute('app_profile');
        }

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('profile_toggle_2fa', $token)) {
            $this->addFlash('error', 'El testimoni de seguretat no és vàlid. Torna-ho a provar.');
            return $this->redirectToRoute('app_profile');
        }

        $newState = !$user->isTwoFactorEnabled();
        $user->setTwoFactorEnabled($newState);
        $user->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $activityLogger?->log(
            user: $user,
            action: UserActivityAction::TWO_FACTOR_TOGGLED,
            description: $newState
                ? 'Activació de verificació en dos passos (2FA) per correu electrònic al perfil'
                : 'Desactivació de verificació en dos passos (2FA) per correu electrònic al perfil',
            details: ['two_factor_enabled' => $newState]
        );

        $this->addFlash(
            'success',
            $newState
                ? 'Doble factor (2FA) activat! En iniciar sessió rebràs un codi de 6 dígits al teu correu.'
                : 'Doble factor (2FA) desactivat per a l\'inici de sessió.'
        );

        return $this->redirectToRoute('app_profile');
    }
}
