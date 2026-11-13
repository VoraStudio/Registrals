<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\PaymentTransaction;
use App\Enum\PaymentStatus;
use App\Infrastructure\Adapter\Payment\PaymentGatewayInterface;
use App\Repository\PaymentTransactionRepository;
use App\Service\Payment\PaymentManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:stripe:sync-sessions',
    description: 'Reconcilia les transaccions pendents de Stripe consultant directament l\'API oficial'
)]
class SyncStripePaymentsCommand extends Command
{
    public function __construct(
        private readonly PaymentTransactionRepository $transactionRepository,
        private readonly PaymentGatewayInterface $paymentGateway,
        private readonly PaymentManager $paymentManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $pending = $this->transactionRepository->findBy([
            'status' => PaymentStatus::PENDING,
        ], ['createdAt' => 'DESC']);

        if (empty($pending)) {
            $io->success('No hi ha cap transacció pendent de reconciliar.');
            return Command::SUCCESS;
        }

        $io->title(sprintf('Reconciliant %d transaccions pendents amb Stripe...', count($pending)));

        $synced = 0;
        foreach ($pending as $tx) {
            $sessionId = $tx->getStripeSessionId();
            if ($sessionId === null || !str_starts_with($sessionId, 'cs_')) {
                continue;
            }

            $io->write(sprintf('Comprovant sessió %s (Tx #%d)... ', $sessionId, $tx->getId() ?? 0));
            $isPaid = $this->paymentManager->syncSessionIfPaid($sessionId, $this->paymentGateway);

            if ($isPaid) {
                $io->writeln('<info>PAGADA I SINCRONITZADA ✓</info>');
                ++$synced;
            } else {
                $io->writeln('<comment>Encara pendent a Stripe</comment>');
            }
        }

        $io->success(sprintf('S\'han sincronitzat %d transaccions correctament.', $synced));

        return Command::SUCCESS;
    }
}

