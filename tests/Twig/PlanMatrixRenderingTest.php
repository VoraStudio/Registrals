<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Entity\Plan;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class PlanMatrixRenderingTest extends TestCase
{
    public function testPlanMacrosFormatDynamicPricesAndQuotasAccurately(): void
    {
        $loader = new FilesystemLoader(dirname(__DIR__, 2) . '/templates');
        $twig = new Environment($loader, ['strict_variables' => true]);

        $planFree = (new Plan('free', 'Pla Free', 0))
            ->setExtraCertificatePriceCents(625)
            ->setExtraTransferPriceCents(310)
            ->setKycIncluded(0)
            ->setKycPriceCents(395);

        $planPlus = (new Plan('plus', 'Pla Plus', 2499))
            ->setAllowOfficialCertificates(true)
            ->setMonthlyCertificatesQuota(12)
            ->setTransfersIncluded(6)
            ->setKycIncluded(1);

        $planUnlimited = (new Plan('unlimited', 'Pla Unlimited / Business', 5999))
            ->setAllowOfficialCertificates(true)
            ->setMonthlyCertificatesQuota(9999)
            ->setTransfersIncluded(9999)
            ->setKycIncluded(9999);

        $template = $twig->createTemplate(<<<'TWIG'
{% import 'partials/_plan_macros.html.twig' as planMacros %}
FREE_CERT={{ planMacros.euros_cents(free.extraCertificatePriceCents)|trim }}
FREE_TXF={{ planMacros.transfers_summary(free)|trim }}
FREE_KYC={{ planMacros.kyc_summary(free)|trim }}
PLUS_PRICE={{ planMacros.euros_cents(plus.priceCents)|trim }}/{{ planMacros.period_short(plus)|trim }}
PLUS_CERT={{ planMacros.certificates_summary(plus)|trim }}
PLUS_TXF={{ planMacros.transfers_summary(plus)|trim }}
PLUS_KYC={{ planMacros.kyc_summary(plus)|trim }}
UNL_PRICE={{ planMacros.euros_cents(unlimited.priceCents)|trim }}/{{ planMacros.period_short(unlimited)|trim }}
UNL_CERT={{ planMacros.certificates_summary(unlimited)|trim }}
UNL_KYC={{ planMacros.kyc_summary(unlimited)|trim }}
TWIG
        );

        $rendered = $template->render([
            'free' => $planFree,
            'plus' => $planPlus,
            'unlimited' => $planUnlimited,
        ]);

        $this->assertStringContainsString('FREE_CERT=6,25 €', $rendered);
        $this->assertStringContainsString('FREE_TXF=3,10 €/u.', $rendered);
        $this->assertStringContainsString('FREE_KYC=3,95 € / tràmit', $rendered);
        $this->assertStringContainsString('PLUS_PRICE=24,99 €/any', $rendered);
        $this->assertStringContainsString('PLUS_CERT=12 inclosos / mes', $rendered);
        $this->assertStringContainsString('PLUS_TXF=6 inclosos / cicle', $rendered);
        $this->assertStringContainsString('PLUS_KYC=1 verificació inclosa', $rendered);
        $this->assertStringContainsString('UNL_PRICE=59,99 €/any', $rendered);
        $this->assertStringContainsString('UNL_CERT=Il·limitats', $rendered);
        $this->assertStringContainsString('UNL_KYC=Inclosa (∞)', $rendered);
    }
}

