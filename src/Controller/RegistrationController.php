<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\PlanRepository;
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
        \Symfony\Bundle\SecurityBundle\Security $security
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

            $user->setPlan($freePlan);

            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            // Registre del consentiment de condicions legals v1.0
            $user->setLegalTermsAcceptedAt(new \DateTimeImmutable());
            $user->setLegalTermsVersion('v1.0');
            $user->setRegistrationIp($request->getClientIp());

            $entityManager->persist($user);
            $entityManager->flush();

            // Inici de sessió automàtic i encaminament segons el pla triat
            $security->login($user, 'form_login', 'main');

            if ($planCode === 'plus') {
                $request->getSession()->remove('intended_plan');
                $this->addFlash('info', 'Compte creat amb èxit! Completa ara l\'activació del teu Pla Plus.');
                return $this->redirectToRoute('app_plan');
            }

            return $this->redirectToRoute('app_onboarding_welcome');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
            'plan' => $planCode,
        ]);
    }
}
