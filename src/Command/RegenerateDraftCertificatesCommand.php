<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\CertificateMode;
use App\Repository\CertificateRepository;
use App\Service\Certificate\CertificateIssuerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:certificates:regenerate-drafts',
    description: 'Regenera els PDFs dels certificats esborranys amb les darreres millores de plantilla (logo, foto)'
)]
class RegenerateDraftCertificatesCommand extends Command
{
    public function __construct(
        private readonly CertificateRepository $certificateRepository,
        private readonly CertificateIssuerService $certificateIssuer
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Regeneració de Certificats Esborranys');

        $drafts = $this->certificateRepository->findBy(['mode' => CertificateMode::DRAFT]);

        if (empty($drafts)) {
            $io->info('No s\'ha trobat cap certificat en mode esborrany.');
            return Command::SUCCESS;
        }

        $io->progressStart(count($drafts));

        $count = 0;
        foreach ($drafts as $cert) {
            try {
                $this->certificateIssuer->regeneratePdf($cert);
                $count++;
            } catch (\Throwable $e) {
                $io->warning(sprintf('Error en regenerar %s: %s', $cert->getCertificateNumber(), $e->getMessage()));
            }
            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success(sprintf('S\'han regenerat amb èxit %d certificat(s) esborrany.', $count));

        return Command::SUCCESS;
    }
}
