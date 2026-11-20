<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\System\ContactEmailRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/parametritzacio/correus')]
#[IsGranted('ROLE_ADMIN')]
class AdminContactEmailController extends AbstractController
{
    #[Route('', name: 'app_admin_contact_emails', methods: ['GET', 'POST'])]
    public function index(Request $request, ContactEmailRegistry $registry): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_contact_emails', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token de seguretat invàlid.');
                return $this->redirectToRoute('app_admin_contact_emails');
            }

            $submitted = $request->request->all('emails');
            $invalid = [];

            foreach (ContactEmailRegistry::DEFINITIONS as $channel => $def) {
                $val = trim((string) ($submitted[$channel] ?? ''));
                if ($val === '' || !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                    $invalid[] = $def['label'];
                    continue;
                }
                $registry->set($channel, $val);
            }

            if ($invalid !== []) {
                $this->addFlash('error', 'Format de correu invàlid per a: ' . implode(', ', $invalid));
            } else {
                $this->addFlash('success', 'Adreces de correu actualitzades a tota la plataforma (landing, legal, verificació i alertes).');
            }

            return $this->redirectToRoute('app_admin_contact_emails');
        }

        return $this->render('admin/contact_email/index.html.twig', [
            'definitions' => ContactEmailRegistry::DEFINITIONS,
            'emails' => $registry->all(),
            'mailerFromAddress' => (string) ($_ENV['MAILER_FROM_ADDRESS'] ?? $_SERVER['MAILER_FROM_ADDRESS'] ?? 'info@registrals.com'),
        ]);
    }
}

