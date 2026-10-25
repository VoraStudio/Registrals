<?php

declare(strict_types=1);

namespace App\Controller;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class InstallAppController extends AbstractController
{
    #[Route('/profile/install-app', name: 'app_profile_install_app')]
    public function index(Request $request): Response
    {
        $user = $this->getUser();

        // URL directa d'accés a l'aplicació mòbil per al codi QR
        $appUrl = $request->getSchemeAndHttpHost() . $this->generateUrl('app_dashboard');

        // Generació del codi QR notarial corporatiu (Ink #1D2F4B) per escanejar des del PC
        $qrCode = new QrCode(
            data: $appUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 260,
            margin: 12,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(29, 47, 75), // Ink #1D2F4B
            backgroundColor: new Color(255, 255, 255)
        );

        $writer = new SvgWriter();
        $qrResult = $writer->write($qrCode);
        $qrDataUri = $qrResult->getDataUri();

        return $this->render('profile/install_app.html.twig', [
            'user' => $user,
            'appUrl' => $appUrl,
            'qrDataUri' => $qrDataUri,
        ]);
    }
}
