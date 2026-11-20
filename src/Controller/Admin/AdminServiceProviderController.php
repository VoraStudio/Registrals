<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ServiceProviderConfig;
use App\Enum\ServiceProviderCategory;
use App\Repository\ServiceProviderConfigRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/parametritzacio/proveidors')]
#[IsGranted('ROLE_ADMIN')]
class AdminServiceProviderController extends AbstractController
{
    #[Route('', name: 'app_admin_service_providers', methods: ['GET'])]
    public function index(
        ServiceProviderConfigRepository $repository
    ): Response {
        $sglangModel = (string) ($_ENV['SGLANG_MODEL_NAME'] ?? $_SERVER['SGLANG_MODEL_NAME'] ?? '');
        $blockchainNetwork = (string) ($_ENV['BLOCKCHAIN_NETWORK'] ?? $_SERVER['BLOCKCHAIN_NETWORK'] ?? '');
        $blockchainKey = (string) ($_ENV['BLOCKCHAIN_HOT_WALLET_PRIVATE_KEY'] ?? $_SERVER['BLOCKCHAIN_HOT_WALLET_PRIVATE_KEY'] ?? '');
        $stripeKey = (string) ($_ENV['STRIPE_SECRET_KEY'] ?? $_SERVER['STRIPE_SECRET_KEY'] ?? '');
        $namirialUrl = (string) ($_ENV['NAMIRIAL_ONBOARDING_BASE_URL'] ?? $_SERVER['NAMIRIAL_ONBOARDING_BASE_URL'] ?? '');
        $signaturitToken = (string) ($_ENV['SIGNATURIT_API_TOKEN'] ?? $_SERVER['SIGNATURIT_API_TOKEN'] ?? '');
        $now = new DateTimeImmutable();
        $activeByCategory = [];
        foreach (ServiceProviderCategory::cases() as $cat) {
            $activeByCategory[$cat->value] = $repository->findActiveForCategory($cat, $now);
        }

        $envSummary = [
            'ai' => $sglangModel !== '' ? sprintf('Model: %s', $sglangModel) : 'Node local per defecte',
            'payment' => str_starts_with($stripeKey, 'sk_live_') ? 'Stripe LIVE actiu' : 'Stripe Test / Sandbox',
            'kyc' => str_contains($namirialUrl, 'test-') ? 'Namirial Demo / Test' : ($namirialUrl !== '' ? 'Namirial Producció' : 'Sense configurar'),
            'blockchain' => sprintf('%s · %s', $blockchainNetwork !== '' ? $blockchainNetwork : 'polygon-pos', trim($blockchainKey) !== '' ? 'Hot Wallet activa' : 'Mode simulat'),
            'tsp' => trim($signaturitToken) !== '' ? 'Token QTSP configurat' : 'Mode Mock / Sandbox',
        ];

        return $this->render('admin/service_provider/index.html.twig', [
            'categories' => ServiceProviderCategory::cases(),
            'activeByCategory' => $activeByCategory,
            'history' => $repository->findAllOrdered(),
            'envSummary' => $envSummary,
        ]);
    }

    #[Route('/nou', name: 'app_admin_service_providers_create', methods: ['POST'])]
    public function create(
        Request $request,
        ServiceProviderConfigRepository $repository,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('service_provider_create', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguretat invàlid.');
            return $this->redirectToRoute('app_admin_service_providers');
        }

        $category = ServiceProviderCategory::tryFrom((string) $request->request->get('category'));
        $providerCode = trim((string) $request->request->get('provider_code'));
        $publicName = trim((string) $request->request->get('public_name'));
        $validFromRaw = trim((string) $request->request->get('valid_from'));
        $validToRaw = trim((string) $request->request->get('valid_to'));
        $notes = trim((string) $request->request->get('notes'));
        $closePrevious = $request->request->getBoolean('close_previous', true);

        if ($category === null || $providerCode === '' || $publicName === '') {
            $this->addFlash('error', 'Cal indicar categoria, codi tècnic i nom públic.');
            return $this->redirectToRoute('app_admin_service_providers');
        }

        try {
            $validFrom = $validFromRaw !== '' ? new DateTimeImmutable($validFromRaw) : new DateTimeImmutable();
            $validTo = $validToRaw !== '' ? new DateTimeImmutable($validToRaw) : null;
        } catch (\Throwable) {
            $this->addFlash('error', 'Format de data invàlid.');
            return $this->redirectToRoute('app_admin_service_providers');
        }

        if ($closePrevious) {
            $previous = $repository->findActiveForCategory($category, $validFrom);
            if ($previous !== null && $previous->getValidTo() === null && $previous->getValidFrom() < $validFrom) {
                $previous->setValidTo($validFrom->modify('-1 second'));
            }
        }

        $config = new ServiceProviderConfig(
            category: $category,
            providerCode: $providerCode,
            publicName: $publicName,
            validFrom: $validFrom,
            validTo: $validTo,
            notes: $notes !== '' ? $notes : null
        );

        $entityManager->persist($config);
        $entityManager->flush();

        $this->addFlash('success', sprintf(
            'Proveïdor "%s" registrat per a %s (vigent des de %s).',
            $publicName,
            $category->label(),
            $validFrom->format('d/m/Y H:i')
        ));

        return $this->redirectToRoute('app_admin_service_providers');
    }

    #[Route('/{id}/editar', name: 'app_admin_service_providers_edit', methods: ['POST'])]
    public function edit(
        int $id,
        Request $request,
        ServiceProviderConfigRepository $repository,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('service_provider_edit_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguretat invàlid.');
            return $this->redirectToRoute('app_admin_service_providers');
        }

        $config = $repository->find($id);
        if (!$config) {
            throw $this->createNotFoundException('Registre de proveïdor no trobat.');
        }

        $publicName = trim((string) $request->request->get('public_name'));
        $providerCode = trim((string) $request->request->get('provider_code'));
        $validFromRaw = trim((string) $request->request->get('valid_from'));
        $validToRaw = trim((string) $request->request->get('valid_to'));
        $notes = trim((string) $request->request->get('notes'));

        if ($publicName === '' || $providerCode === '') {
            $this->addFlash('error', 'El nom públic i el codi tècnic són obligatoris.');
            return $this->redirectToRoute('app_admin_service_providers');
        }

        try {
            if ($validFromRaw !== '') {
                $config->setValidFrom(new DateTimeImmutable($validFromRaw));
            }
            $config->setValidTo($validToRaw !== '' ? new DateTimeImmutable($validToRaw) : null);
        } catch (\Throwable) {
            $this->addFlash('error', 'Format de data invàlid.');
            return $this->redirectToRoute('app_admin_service_providers');
        }

        $config->setPublicName($publicName);
        $config->setProviderCode($providerCode);
        $config->setNotes($notes !== '' ? $notes : null);

        $entityManager->flush();

        $this->addFlash('success', sprintf('Actualitzat "%s" (%s).', $publicName, $config->getCategory()->label()));

        return $this->redirectToRoute('app_admin_service_providers');
    }
}

