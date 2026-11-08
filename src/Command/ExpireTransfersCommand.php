<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Transfer\TransferExpirationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:transfers:expire',
    description: 'Caduca automàticament els traspassos C2C pendents que han superat el període de 72 hores.'
)]
class ExpireTransfersCommand extends Command
{
    public function __construct(
        private readonly TransferExpirationService $expirationService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Comprovació de Caducitat de Traspassos C2C (72 hores)');

        $count = $this->expirationService->expireTransfers();

        if ($count > 0) {
            $io->success(sprintf('S\'han caducat i reconciliat amb èxit %d traspàs(sos) vençut(s). Els béns han tornat al patrimoni dels seus propietaris.', $count));
        } else {
            $io->info('No s\'ha trobat cap traspàs pendent vençut per caducar.');
        }

        return Command::SUCCESS;
    }
}

