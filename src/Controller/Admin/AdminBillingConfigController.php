<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\BillingConfig;
use App\Repository\BillingConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/configuracio-fiscal')]
#[IsGranted('ROLE_ADMIN')]
class AdminBillingConfigController extends AbstractController
{
    #[Route('', name: 'app_admin_billing_config', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        BillingConfigRepository $configRepository,
        EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        $activeConfig = $configRepository->findActiveConfig();
        if ($activeConfig === null) {
            $activeConfig = new BillingConfig(
                legalName: 'Albert (Registrals)',
                taxId: '00000000T',
                address: 'Carrer de la Pau 1',
                postalCode: '08001',
                city: 'Barcelona',
                province: 'Barcelona',
                billingEmail: 'facturacio@registrals.cat',
                countryCode: 'ES'
            );
            $entityManager->persist($activeConfig);
            $entityManager->flush();
        }

        if ($request->isMethod('POST')) {
            $token = new CsrfToken('admin_billing_config_update', (string) $request->request->get('_csrf_token'));
            if (!$csrfTokenManager->isTokenValid($token)) {
                $this->addFlash('error', 'Token de seguretat CSRF no vàlid.');
                return $this->redirectToRoute('app_admin_billing_config');
            }

            $newLegalName = trim((string) $request->request->get('legal_name', $activeConfig->getLegalName()));
            $newTaxId = strtoupper(trim((string) $request->request->get('tax_id', $activeConfig->getTaxId())));
            $forceNewVersion = $request->request->has('create_new_version');

            $isEntityChanged = ($newTaxId !== $activeConfig->getTaxId() || $newLegalName !== $activeConfig->getLegalName());

            if ($isEntityChanged || $forceNewVersion) {
                // Arxivament de la versió anterior (garanteix traçabilitat històrica absoluta)
                $activeConfig->setIsActive(false);
                $activeConfig->setUpdatedAt(new \DateTimeImmutable());

                // Creació de la nova versió vigent
                $newConfig = new BillingConfig(
                    legalName: $newLegalName,
                    taxId: $newTaxId,
                    address: trim((string) $request->request->get('address', $activeConfig->getAddress())),
                    postalCode: trim((string) $request->request->get('postal_code', $activeConfig->getPostalCode())),
                    city: trim((string) $request->request->get('city', $activeConfig->getCity())),
                    province: trim((string) $request->request->get('province', $activeConfig->getProvince())),
                    billingEmail: trim((string) $request->request->get('billing_email', $activeConfig->getBillingEmail())),
                    countryCode: strtoupper(trim((string) $request->request->get('country_code', $activeConfig->getCountryCode())))
                );

                $mercantileRegistry = trim((string) $request->request->get('mercantile_registry', ''));
                $newConfig->setMercantileRegistry($mercantileRegistry !== '' ? $mercantileRegistry : null);
                $newConfig->setDefaultVatRate(number_format((float) $request->request->get('default_vat_rate', 21.0), 2, '.', ''));
                $newConfig->setDefaultIrpfRate(number_format((float) $request->request->get('default_irpf_rate', 0.0), 2, '.', ''));
                $newConfig->setSeriesComplete(trim((string) $request->request->get('series_complete', 'REG-')));
                $newConfig->setSeriesSimplified(trim((string) $request->request->get('series_simplified', 'FS-')));
                $newConfig->setSeriesRectification(trim((string) $request->request->get('series_rectification', 'R-')));

                // Si ha canviat de NIF/CIF o de nom social, es reinicien comptadors a 1; altrament es mantenen
                $nextComplete = $isEntityChanged ? 1 : $activeConfig->getNextNumberComplete();
                $nextSimplified = $isEntityChanged ? 1 : $activeConfig->getNextNumberSimplified();
                $nextRectification = $isEntityChanged ? 1 : $activeConfig->getNextNumberRectification();
                $newConfig->setNextNumberComplete($nextComplete);
                $newConfig->setNextNumberSimplified($nextSimplified);
                $newConfig->setNextNumberRectification($nextRectification);

                $footerLegalText = trim((string) $request->request->get('footer_legal_text', ''));
                $newConfig->setFooterLegalText($footerLegalText !== '' ? $footerLegalText : null);
                $newConfig->setIsActive(true);

                $entityManager->persist($newConfig);
                $entityManager->flush();

                $this->addFlash('success', sprintf(
                    'S\'ha creat la nova versió vigent del perfil emissor #%d («%s» / %s) i s\'ha arxivat l\'anterior (#%d) mantenint l\'històric inalterat.',
                    $newConfig->getId(),
                    $newConfig->getLegalName(),
                    $newConfig->getTaxId(),
                    $activeConfig->getId()
                ));
            } else {
                // Actualització in-place quan són canvis menors de la mateixa entitat (adreça, email, peu)
                $activeConfig->setAddress(trim((string) $request->request->get('address', $activeConfig->getAddress())));
                $activeConfig->setPostalCode(trim((string) $request->request->get('postal_code', $activeConfig->getPostalCode())));
                $activeConfig->setCity(trim((string) $request->request->get('city', $activeConfig->getCity())));
                $activeConfig->setProvince(trim((string) $request->request->get('province', $activeConfig->getProvince())));
                $activeConfig->setCountryCode(strtoupper(trim((string) $request->request->get('country_code', $activeConfig->getCountryCode()))));

                $mercantileRegistry = trim((string) $request->request->get('mercantile_registry', ''));
                $activeConfig->setMercantileRegistry($mercantileRegistry !== '' ? $mercantileRegistry : null);

                $activeConfig->setBillingEmail(trim((string) $request->request->get('billing_email', $activeConfig->getBillingEmail())));
                $activeConfig->setDefaultVatRate(number_format((float) $request->request->get('default_vat_rate', 21.0), 2, '.', ''));
                $activeConfig->setDefaultIrpfRate(number_format((float) $request->request->get('default_irpf_rate', 0.0), 2, '.', ''));

                $activeConfig->setSeriesComplete(trim((string) $request->request->get('series_complete', 'REG-')));
                $activeConfig->setSeriesSimplified(trim((string) $request->request->get('series_simplified', 'FS-')));
                $activeConfig->setSeriesRectification(trim((string) $request->request->get('series_rectification', 'R-')));

                $footerLegalText = trim((string) $request->request->get('footer_legal_text', ''));
                $activeConfig->setFooterLegalText($footerLegalText !== '' ? $footerLegalText : null);

                $activeConfig->setUpdatedAt(new \DateTimeImmutable());
                $entityManager->flush();

                $this->addFlash('success', 'Les dades del perfil fiscal vigent s\'han actualitzat correctament.');
            }

            return $this->redirectToRoute('app_admin_billing_config');
        }

        $allConfigs = $configRepository->findAllOrdered();

        return $this->render('admin/billing_config/index.html.twig', [
            'config' => $activeConfig,
            'allConfigs' => $allConfigs,
        ]);
    }
}
