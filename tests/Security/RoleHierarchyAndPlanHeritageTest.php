<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\Certificate;
use App\Entity\LegacyContact;
use App\Entity\Plan;
use App\Entity\User;
use App\Enum\CertificateMode;
use App\Enum\CertificateScope;
use App\Enum\CertificateStatus;
use App\Form\Admin\PlanType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Twig\Environment;

class RoleHierarchyAndPlanHeritageTest extends KernelTestCase
{
    public function testRoleHierarchySeparatesGestorFromAdmin(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var RoleHierarchyInterface $hierarchy */
        $hierarchy = $container->get(RoleHierarchyInterface::class);

        $adminRoles = $hierarchy->getReachableRoleNames(['ROLE_ADMIN']);
        $this->assertContains('ROLE_ADMIN', $adminRoles);
        $this->assertContains('ROLE_GESTOR', $adminRoles);
        $this->assertContains('ROLE_USER', $adminRoles);

        $gestorRoles = $hierarchy->getReachableRoleNames(['ROLE_GESTOR']);
        $this->assertContains('ROLE_GESTOR', $gestorRoles);
        $this->assertContains('ROLE_USER', $gestorRoles);
        $this->assertNotContains('ROLE_ADMIN', $gestorRoles);
    }

    public function testPlanTypeMapsLegacyHeritageParameters(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = $container->get('form.factory');

        $plan = (new Plan('plus', 'Pla Plus', 2200))
            ->setAllowLegacyDesignation(false)
            ->setMaxLegacyContacts(0)
            ->setLegacyClaimOpeningFeeCents(1900);

        $form = $formFactory->create(PlanType::class, $plan, ['csrf_protection' => false]);
        $form->submit([
            'name' => 'Pla Plus',
            'priceCents' => '2200',
            'billingPeriod' => 'yearly',
            'maxActiveItems' => '100',
            'maxYearlyRegistrations' => '120',
            'maxPhotosPerItem' => '5',
            'maxEvidencesPerItem' => '5',
            'aiAssistanceQuota' => '100',
            'monthlyCertificatesQuota' => '10',
            'extraCertificatePriceCents' => '590',
            'kycIncluded' => '1',
            'kycPriceCents' => '350',
            'transfersIncluded' => '5',
            'extraTransferPriceCents' => '0',
            'maxLegacyContacts' => '2',
            'legacyClaimOpeningFeeCents' => '2500',
            'allowOfficialCertificates' => '1',
            'allowLegacyDesignation' => '1',
            'aiAssistanceEnabled' => '1',
            'isActive' => '1',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertTrue($plan->isAllowLegacyDesignation());
        $this->assertSame(2, $plan->getMaxLegacyContacts());
        $this->assertSame(2500, $plan->getLegacyClaimOpeningFeeCents());
    }

    public function testCertificateTileRendersSafelyWithoutItemForLegacyScope(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var Environment $twig */
        $twig = $container->get('twig');

        $owner = (new User())->setEmail('titular@registrals.cat')->setFirstName('Anna')->setLastName('Puig');
        $contact = new LegacyContact($owner, 'Maria Puig', 'maria@exemple.cat');
        $cert = new Certificate(
            item: null,
            issuedBy: $owner,
            certificateNumber: 'CERT-2026-000501',
            mode: CertificateMode::OFFICIAL,
            pdfFilePath: 'certificates/2026/LEGAT_CERT-2026-000501.pdf',
            pdfSha256: hash('sha256', 'pdf'),
            itemFingerprintAtIssue: hash('sha256', 'fp'),
            status: CertificateStatus::VALID,
            scope: CertificateScope::LEGACY_INVENTORY,
            legacyContact: $contact
        );

        $html = $twig->render('certificate/_certificate_tile.html.twig', [
            'cert' => $cert,
            'qr' => null,
        ]);

        $this->assertStringContainsString('Inventari Global L', $html);
        $this->assertStringContainsString('CERT-2026-000501', $html);
        $this->assertStringNotContainsString('/app/items/', $html);
    }
}
