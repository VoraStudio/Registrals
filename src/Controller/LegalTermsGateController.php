<?php

declare(strict_types=1);

namespace App\Controller;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\Entity\User;
use App\EventSubscriber\LegalGateSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class LegalTermsGateController extends AbstractController
{
    #[Route('/app/legal/acceptar-condicions', name: 'app_legal_accept_terms', methods: ['GET', 'POST'])]
    public function acceptTerms(
        Request $request,
        EntityManagerInterface $entityManager,
        UserActivityLogger $activityLogger
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // Si l'usuari ja té les condicions acceptades en la versió vigent, va directe al Dashboard
        if ($user->hasAcceptedLegalTerms(LegalGateSubscriber::CURRENT_TERMS_VERSION)) {
            return $this->redirectToRoute('app_dashboard');
        }

        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_token', '');
            if (!$this->isCsrfTokenValid('legal_terms_accept', $token)) {
                $this->addFlash('error', 'El testimoni de seguretat no és vàlid. Si us plau, torna-ho a provar.');
                return $this->redirectToRoute('app_legal_accept_terms');
            }

            $user->setLegalTermsAcceptedAt(new \DateTimeImmutable());
            $user->setLegalTermsVersion(LegalGateSubscriber::CURRENT_TERMS_VERSION);
            $user->setRegistrationIp($request->getClientIp());

            $entityManager->flush();

            $activityLogger->log(
                user: $user,
                action: UserActivityAction::LEGAL_TERMS_ACCEPTED,
                description: sprintf('Acceptació solemne de les condicions de custòdia i certificació digital (%s)', LegalGateSubscriber::CURRENT_TERMS_VERSION),
                details: [
                    'version' => LegalGateSubscriber::CURRENT_TERMS_VERSION,
                    'accepted_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                ]
            );

            $this->addFlash('success', 'Condicions de custòdia i certificació digital acceptades correctament. Benvingut/da al teu espai privat.');

            if ($request->getSession()->get('intended_plan') === 'plus') {
                $request->getSession()->remove('intended_plan');
                $this->addFlash('info', 'Compte creat amb èxit! Completa ara l\'activació del teu Pla Plus.');
                return $this->redirectToRoute('app_plan');
            }

            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('legal/accept_terms.html.twig', [
            'termsVersion' => LegalGateSubscriber::CURRENT_TERMS_VERSION,
            'user' => $user,
        ]);
    }
}
