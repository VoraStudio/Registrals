<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Entity\LegacyContact;
use App\Entity\Plan;
use App\Entity\SuccessionClaim;
use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

class ProfileLegacySectionRenderingTest extends TestCase
{
    public function testAcceptedAndPendingIncomingCardsRenderGuidanceAndDynamicOpeningFee(): void
    {
        $twig = $this->createTwigEnvironment();

        $ownerPlan = (new Plan('plus', 'Pla Plus', 2200))
            ->setAllowLegacyDesignation(true)
            ->setLegacyClaimOpeningFeeCents(2450);

        $owner = (new User())
            ->setEmail('titular@registrals.cat')
            ->setFirstName('Carles')
            ->setLastName('Test')
            ->setPlan($ownerPlan);

        $recipient = (new User())
            ->setEmail('albert@registrals.cat')
            ->setFirstName('Albert')
            ->setLastName('Receptor');

        $accepted = new LegacyContact($owner, 'Albert Receptor', 'albert@registrals.cat', 'veí del 3r');
        $accepted->accept($recipient, '127.0.0.1');

        $pending = new LegacyContact($owner, 'Albert Pendent', 'albert2@registrals.cat');
        $pending->issueInvitationToken(hash('sha256', 'tok'), (new \DateTimeImmutable())->modify('+14 days'));

        $html = $twig->render('profile/_legacy_incoming_section.html.twig', [
            'incomingLegacyContacts' => [$accepted, $pending],
            'isPrimarySection' => true,
        ]);

        $this->assertStringContainsString('Com funciona a partir d\'ara?', $html);
        $this->assertStringContainsString('Sense cost ni accions en vida (0 €)', $html);
        $this->assertStringContainsString('24,50 €', $html);
        $this->assertStringContainsString('finestra preventiva de 72 h', $html);
        $this->assertStringContainsString('Comunicar defunció i obrir expedient →', $html);
        $this->assertStringContainsString('Acceptar aquesta designació és <strong>gratuït (0 €, Pla Free)</strong>', $html);
        $this->assertStringNotContainsString('notarial', mb_strtolower($html));

        $claim = (new SuccessionClaim(
            'LEG-2026-000777',
            null,
            $owner,
            'Albert Receptor',
            'albert@registrals.cat',
            '12345678Z',
            new \DateTimeImmutable(),
            'legacy/claims/LEG-2026-000777/d.pdf',
            str_repeat('1', 64),
            'legacy/claims/LEG-2026-000777/t.pdf',
            str_repeat('2', 64),
            $accepted,
            $recipient,
        ));
        $claim->activateOwnerNoticeWindow(2450, 'pi_1', new \DateTimeImmutable('+70 hours'));

        $htmlWithClaim = $twig->render('profile/_legacy_incoming_section.html.twig', [
            'incomingLegacyContacts' => [$accepted],
            'incomingClaimsByContactUuid' => [$accepted->getUuid()->toRfc4122() => $claim],
            'isPrimarySection' => true,
        ]);

        $this->assertStringContainsString('LEG-2026-000777', $htmlWithClaim);
        $this->assertStringContainsString('Finestra preventiva de 72 h activa', $htmlWithClaim);
        $this->assertStringContainsString('Veure Estat de l\'Expedient →', $htmlWithClaim);
        $this->assertStringNotContainsString('Comunicar defunció i obrir expedient →', $htmlWithClaim);
    }

    public function testPlusPromoPartialRendersCompactFooterModeAndFullCtaMode(): void
    {
        $twig = $this->createTwigEnvironment();

        $compactHtml = $twig->render('profile/_legacy_plus_promo.html.twig', ['compact' => true]);
        $this->assertStringContainsString('Vols designar també una persona de confiança per als teus propis béns?', $compactHtml);
        $this->assertStringContainsString('Veure Pla Plus →', $compactHtml);
        $this->assertStringNotContainsString('Passar al Pla Plus →', $compactHtml);

        $fullHtml = $twig->render('profile/_legacy_plus_promo.html.twig', ['compact' => false]);
        $this->assertStringContainsString('Continuïtat Patrimonial del Pla Plus:', $fullHtml);
        $this->assertStringContainsString('Passar al Pla Plus →', $fullHtml);
    }

    private function createTwigEnvironment(): Environment
    {
        $loader = new FilesystemLoader(dirname(__DIR__, 2) . '/templates');
        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addFunction(new TwigFunction('path', static fn (string $route, array $params = []): string => '/' . $route));
        $twig->addFunction(new TwigFunction('csrf_token', static fn (string $id): string => 'csrf_' . $id));

        return $twig;
    }
}

