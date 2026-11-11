<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\LegacyClaimController;
use App\Entity\Certificate;
use App\Entity\LegacyContact;
use App\Entity\Plan;
use App\Entity\SuccessionClaim;
use App\Entity\User;
use App\Enum\CertificateMode;
use App\Enum\CertificateScope;
use App\Enum\CertificateStatus;
use App\Enum\PaymentType;
use App\Infrastructure\Adapter\Payment\PaymentGatewayInterface;
use App\Repository\SuccessionClaimRepository;
use App\Service\Legacy\LegacyCertificateIssuerService;
use App\Service\Legacy\SuccessionClaimWorkflowService;
use App\Service\Payment\PaymentManager;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
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

    public function testSendOtpAndVerifyOtpAndMockCheckoutFlow(): void
    {
        $owner = (new User())
            ->setEmail('anna@registrals.cat')
            ->setFirstName('Anna')
            ->setLastName('Puig')
            ->setPlan((new Plan('plus', 'Pla Plus', 2499))->setLegacyClaimOpeningFeeCents(1900));
        (new \ReflectionProperty(User::class, 'id'))->setValue($owner, 1);

        $contact = new LegacyContact($owner, 'Maria Soler', 'maria@exemple.cat');
        $cert = new Certificate(
            item: null,
            issuedBy: $owner,
            certificateNumber: 'CERT-2026-000990',
            mode: CertificateMode::OFFICIAL,
            pdfFilePath: 'certificates/2026/LEGAT_OK.pdf',
            pdfSha256: str_repeat('c', 64),
            itemFingerprintAtIssue: str_repeat('d', 64),
            status: CertificateStatus::VALID,
            scope: CertificateScope::LEGACY_INVENTORY,
            legacyContact: $contact,
        );
        (new \ReflectionProperty(Certificate::class, 'id'))->setValue($cert, 55);

        $legacyIssuer = $this->createMock(LegacyCertificateIssuerService::class);
        $legacyIssuer->method('resolveClaimByRawToken')->willReturn([
            'certificate' => $cert,
            'state' => 'valid',
            'isCurrentSnapshot' => true,
        ]);

        $token = str_repeat('e', 64);
        $session = new Session(new MockArraySessionStorage());

        $sentOtp = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->willReturnCallback(function (\Symfony\Bridge\Twig\Mime\TemplatedEmail $mail) use (&$sentOtp): void {
                $sentOtp = $mail->getContext()['otpCode'] ?? null;
            });

        $claim = new SuccessionClaim(
            'LEG-2026-000200',
            $cert,
            $owner,
            'Maria Soler',
            'maria@exemple.cat',
            '12345678Z',
            new \DateTimeImmutable(),
            'legacy/claims/LEG-2026-000200/def.pdf',
            str_repeat('1', 64),
            'legacy/claims/LEG-2026-000200/tit.pdf',
            str_repeat('2', 64),
        );
        (new \ReflectionProperty(SuccessionClaim::class, 'id'))->setValue($claim, 88);

        $claimRepo = $this->createMock(SuccessionClaimRepository::class);
        $claimRepo->method('findByUuid')->willReturn($claim);

        $gateway = $this->createMock(PaymentGatewayInterface::class);
        $gateway->expects($this->once())
            ->method('createSuccessionClaimCheckout')
            ->willReturn([
                'sessionId' => 'cs_mock_claim_123',
                'url' => '/herencia/reclamar/' . $token . '/retorn?mock=1',
            ]);

        $paymentManager = $this->createMock(PaymentManager::class);
        $paymentManager->expects($this->once())->method('createPendingTransaction');
        $paymentManager->expects($this->once())->method('processWebhook')->willReturn(true);

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn(true);

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/herencia/reclamar/' . $token);

        $request = new Request([], [
            '_token' => 'ok',
            'claimant_name' => 'Maria Soler',
            'claimant_email' => 'maria@exemple.cat',
            'claimant_tax_id' => '12345678Z',
        ]);
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn (string $id): bool => in_array($id, ['security.csrf.token_manager', 'router', 'request_stack'], true));
        $container->method('get')->willReturnCallback(fn (string $id) => match ($id) {
            'security.csrf.token_manager' => $csrf,
            'router' => $router,
            'request_stack' => $stack,
            default => null,
        });

        $controller = new LegacyClaimController(
            legacyCertificateIssuer: $legacyIssuer,
            workflowService: $this->createMock(SuccessionClaimWorkflowService::class),
            claimRepository: $claimRepo,
            paymentGateway: $gateway,
            paymentManager: $paymentManager,
            mailer: $mailer,
        );
        $controller->setContainer($container);

        $otpResponse = $controller->sendOtp($token, $request);
        $this->assertInstanceOf(RedirectResponse::class, $otpResponse);
        $this->assertNotNull($sentOtp);

        $verifyReq = new Request([], ['_token' => 'ok', 'otp_code' => $sentOtp]);
        $verifyReq->setSession($session);
        $controller->verifyOtp($token, $verifyReq);

        $otpSession = $session->get('legacy_claim_otp_' . hash('sha256', $token));
        $this->assertNotEmpty($otpSession['verifiedAt']);

        $payReq = new Request([], ['_token' => 'ok', 'claim_uuid' => $claim->getUuid()->toRfc4122()]);
        $payReq->setSession($session);
        $payResponse = $controller->checkoutOpeningFee($token, $payReq);

        $this->assertInstanceOf(RedirectResponse::class, $payResponse);
        $this->assertStringContainsString('mock=1', $payResponse->getTargetUrl());
    }
}
