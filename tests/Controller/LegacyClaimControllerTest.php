<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\LegacyClaimController;
use App\Entity\Certificate;
use App\Entity\LegacyContact;
use App\Entity\Plan;
use App\Entity\User;
use App\Enum\CertificateMode;
use App\Enum\CertificateScope;
use App\Enum\CertificateStatus;
use App\Service\Legacy\LegacyCertificateIssuerService;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class LegacyClaimControllerTest extends TestCase
{
    private function createContainer(string $expectedTemplate, callable $assertParams): ContainerInterface
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->willReturnCallback(function (string $template, array $params) use ($expectedTemplate, $assertParams): string {
                $this->assertSame($expectedTemplate, $template);
                $assertParams($params);

                return '<html>rendered</html>';
            });

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn (string $id): bool => $id === 'twig');
        $container->method('get')->willReturnCallback(fn (string $id) => $id === 'twig' ? $twig : null);

        return $container;
    }

    public function testInvalidTokenReturnsNotFound(): void
    {
        $legacyIssuer = $this->createMock(LegacyCertificateIssuerService::class);
        $legacyIssuer->method('resolveClaimByRawToken')->willReturn([
            'certificate' => null,
            'state' => 'invalid',
            'isCurrentSnapshot' => false,
        ]);

        $controller = new LegacyClaimController($legacyIssuer);
        $controller->setContainer($this->createContainer('legacy/claim_entry.html.twig', function (array $params) {
            $this->assertSame('invalid', $params['state']);
            $this->assertNull($params['certificate']);
        }));

        $response = $controller->show('badtoken');

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testRevokedCertificateReturnsGone(): void
    {
        $owner = (new User())
            ->setEmail('anna@registrals.cat')
            ->setFirstName('Anna')
            ->setLastName('Puig')
            ->setPlan((new Plan('plus', 'Pla Plus', 2499))->setLegacyClaimOpeningFeeCents(1900));
        $contact = new LegacyContact($owner, 'Maria Puig', 'maria@exemple.cat');
        $cert = new Certificate(
            item: null,
            issuedBy: $owner,
            certificateNumber: 'CERT-2026-000970',
            mode: CertificateMode::OFFICIAL,
            pdfFilePath: 'certificates/2026/LEGAT_REV.pdf',
            pdfSha256: str_repeat('a', 64),
            itemFingerprintAtIssue: str_repeat('b', 64),
            status: CertificateStatus::REVOKED,
            scope: CertificateScope::LEGACY_INVENTORY,
            legacyContact: $contact,
        );

        $legacyIssuer = $this->createMock(LegacyCertificateIssuerService::class);
        $legacyIssuer->method('resolveClaimByRawToken')->willReturn([
            'certificate' => $cert,
            'state' => 'revoked',
            'isCurrentSnapshot' => false,
        ]);

        $controller = new LegacyClaimController($legacyIssuer);
        $controller->setContainer($this->createContainer('legacy/claim_entry.html.twig', function (array $params) {
            $this->assertSame('revoked', $params['state']);
        }));

        $response = $controller->show(str_repeat('a', 64));

        $this->assertSame(Response::HTTP_GONE, $response->getStatusCode());
    }

    public function testValidTokenRendersClaimEntryWithMaskedIdentity(): void
    {
        $owner = (new User())
            ->setEmail('anna@registrals.cat')
            ->setFirstName('Anna')
            ->setLastName('Puig')
            ->setPlan((new Plan('plus', 'Pla Plus', 2499))->setLegacyClaimOpeningFeeCents(2500));
        $beneficiary = (new User())
            ->setEmail('maria@exemple.cat')
            ->setFirstName('Maria')
            ->setLastName('Soler');
        $contact = (new LegacyContact($owner, 'Maria Soler', 'maria@exemple.cat'))
            ->accept($beneficiary);
        $cert = new Certificate(
            item: null,
            issuedBy: $owner,
            certificateNumber: 'CERT-2026-000971',
            mode: CertificateMode::OFFICIAL,
            pdfFilePath: 'certificates/2026/LEGAT_OK.pdf',
            pdfSha256: str_repeat('c', 64),
            itemFingerprintAtIssue: str_repeat('d', 64),
            status: CertificateStatus::VALID,
            scope: CertificateScope::LEGACY_INVENTORY,
            legacyContact: $contact,
            includedItemCuisSnapshot: [['cui' => 'REG-1'], ['cui' => 'REG-2'], ['cui' => 'REG-3']],
        );

        $legacyIssuer = $this->createMock(LegacyCertificateIssuerService::class);
        $legacyIssuer->method('resolveClaimByRawToken')->willReturn([
            'certificate' => $cert,
            'state' => 'valid',
            'isCurrentSnapshot' => true,
        ]);

        $controller = new LegacyClaimController($legacyIssuer);
        $controller->setContainer($this->createContainer('legacy/claim_entry.html.twig', function (array $params) {
            $this->assertSame('valid', $params['state']);
            $this->assertSame('A*** P***', $params['maskedOwnerName']);
            $this->assertSame('M**** S****', $params['maskedContactName']);
            $this->assertSame(2500, $params['openingFeeCents']);
            $this->assertSame(3, $params['itemsCount']);
        }));

        $response = $controller->show(str_repeat('b', 64));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }
}
