<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class FlashRenderingTest extends TestCase
{
    private function createTwigWithSession(Session $session): Environment
    {
        $request = new Request();
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $app = new AppVariable();
        $app->setRequestStack($requestStack);

        $loader = new FilesystemLoader(dirname(__DIR__, 2) . '/templates');
        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addGlobal('app', $app);

        return $twig;
    }

    public function testSuccessAndInfoFlowToToastWhileErrorAndWarningStayPersistent(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $flashBag = $session->getFlashBag();
        $flashBag->add('success', 'Pla Plus actualitzat en calent.');
        $flashBag->add('info', "No hi ha missatges pendents a la cua d'errors.");
        $flashBag->add('warning', 'El servei GeoIP no ha respost.');
        $flashBag->add('error', 'Token de seguretat CSRF invàlid.');

        $twig = $this->createTwigWithSession($session);

        $persistentHtml = $twig->render('partials/_persistent_flashes.html.twig', [
            'margin_class' => 'mb-6',
        ]);

        // Només error i warning s'han d'imprimir com a alertes persistents amb estils diferenciats
        $this->assertStringContainsString('data-persistent-flash', $persistentHtml);
        $this->assertStringContainsString('Token de seguretat CSRF invàlid.', $persistentHtml);
        $this->assertStringContainsString('bg-red-50 text-red-900 border border-red-200', $persistentHtml);
        $this->assertStringContainsString('El servei GeoIP no ha respost.', $persistentHtml);
        $this->assertStringContainsString('bg-amber-50 text-amber-900 border border-amber-200', $persistentHtml);
        $this->assertStringNotContainsString('Pla Plus actualitzat en calent.', $persistentHtml);
        $this->assertStringNotContainsString('cua d\'errors', $persistentHtml);

        $toastHtml = $twig->render('partials/_toast.html.twig');
        $dispatched = $this->extractDispatchedToasts($toastHtml);

        // Success i info arriben intactes a RegistralsToast amb títols coherents per a /admin i /app
        $this->assertSame([
            ['label' => 'success', 'message' => 'Pla Plus actualitzat en calent.'],
            ['label' => 'info', 'message' => "No hi ha missatges pendents a la cua d'errors."],
        ], $dispatched);
        $this->assertStringContainsString("'Informació'", $toastHtml);
        $this->assertStringNotContainsString('Patrimoni Actualitzat', $toastHtml);

        // Els errors i warnings ja consumits no es dupliquen en Toast
        $this->assertStringNotContainsString('CSRF', $toastHtml);
        $this->assertStringNotContainsString('GeoIP', $toastHtml);
    }

    public function testEmptyErrorAndWarningDoNotRenderBanners(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->getFlashBag()->add('success', 'Configuració desada.');

        $twig = $this->createTwigWithSession($session);

        $persistentHtml = $twig->render('partials/_persistent_flashes.html.twig');
        $this->assertSame('', trim($persistentHtml));

        $toastHtml = $twig->render('partials/_toast.html.twig');
        $this->assertSame([
            ['label' => 'success', 'message' => 'Configuració desada.'],
        ], $this->extractDispatchedToasts($toastHtml));
    }

    /**
     * @return list<array{label: string, message: string}>
     */
    private function extractDispatchedToasts(string $html): array
    {
        preg_match_all(
            "/const rawLabel = '([^']+)';.*?window\\.RegistralsToast\\.show\\(title, '([^']+)', type, 5000\\);/s",
            $html,
            $matches,
            PREG_SET_ORDER
        );

        $result = [];
        foreach ($matches as $match) {
            $decoded = json_decode('"' . str_replace('"', '\\"', $match[2]) . '"', true);
            $result[] = [
                'label' => $match[1],
                'message' => is_string($decoded) ? $decoded : $match[2],
            ];
        }

        return $result;
    }
}
