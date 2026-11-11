<?php

declare(strict_types=1);

namespace App\Controller;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\Entity\User;
use App\Enum\EmailOtpPurpose;
use App\Form\RegistrationFormType;
use App\Repository\PlanRepository;
use App\Service\Security\AuthChallengeStorage;
use App\Service\Security\EmailOtpService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        PlanRepository $planRepository,
        EmailOtpService $emailOtpService,
        AuthChallengeStorage $challengeStorage,
        ?UserActivityLogger $activityLogger = null
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        if ($request->isMethod('POST')) {
            $postPlan = (string) ($request->request->get('plan') ?? $request->query->get('plan') ?? 'free');
            $planCode = strtolower($postPlan) === 'plus' ? 'plus' : 'free';
        } else {
            // Peticions GET: el pla depèn estrictament del query parameter. Sense paràmetre o ?plan=free és SEMPRE Free.
            $queryPlan = (string) $request->query->get('plan');
            $planCode = strtolower($queryPlan) === 'plus' ? 'plus' : 'free';

            if ($planCode === 'plus') {
                $request->getSession()->set('intended_plan', 'plus');
            } else {
                $request->getSession()->remove('intended_plan');
            }
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $freePlan = $planRepository->findDefaultFreePlan();
            if ($freePlan === null) {
                throw new \RuntimeException('El pla Free per defecte no està configurat a la base de dades.');
            }

            $user->setEmail(mb_strtolower(trim($user->getEmail())));
            $user->setPlan($freePlan);

            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));
            $user->setRegistrationIp($request->getClientIp());

            $entityManager->persist($user);
            $entityManager->flush();

            $activityLogger?->log(
                user: $user,
                action: UserActivityAction::REGISTER,
                description: sprintf('Alta inicial de compte pendent de verificació per correu (%s)', $user->getEmail()),
                details: ['plan_intended' => $planCode, 'ip' => $request->getClientIp()]
            );

            $otpResult = $emailOtpService->issueOtp($user, EmailOtpPurpose::REGISTRATION_VERIFY);
            $userId = $user->getId();
            if ($userId !== null && $request->hasSession()) {
                $challengeStorage->store($request->getSession(), $userId, EmailOtpPurpose::REGISTRATION_VERIFY, false);
                if ($planCode === 'plus') {
                    $request->getSession()->set('intended_plan', 'plus');
                } else {
                    $request->getSession()->remove('intended_plan');
                }
            }

            if ($otpResult['error'] !== null) {
                $this->addFlash('error', $otpResult['error']);
            } else {
                $this->addFlash('info', 'T\'hem enviat un codi de 6 dígits al teu correu per validar el registre.');
            }

            return $this->redirectToRoute('app_register_verify_email');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
            'plan' => $planCode,
        ]);
    }
}
