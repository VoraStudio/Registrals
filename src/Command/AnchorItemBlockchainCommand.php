<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\BlockchainActionType;
use App\Message\AnchorBlockchainMessage;
use App\MessageHandler\AnchorBlockchainMessageHandler;
use App\Repository\ItemRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:item:anchor-blockchain',
    description: 'Envia o executa la notarització criptogràfica d\'un bé a Polygon Blockchain'
)]
class AnchorItemBlockchainCommand extends Command
{
    public function __construct(
        private readonly ItemRepository $itemRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly AnchorBlockchainMessageHandler $handler
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('cui', InputArgument::REQUIRED, 'CUI del bé (ex: REG-2026-ZVCFXY)')
            ->addOption('sync', null, InputOption::VALUE_NONE, 'Executa la notarització immediatament de forma síncrona en comptes d\'encuar-la');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $cui = (string) $input->getArgument('cui');
        $sync = (bool) $input->getOption('sync');

        $item = $this->itemRepository->findOneByCui($cui);

        if (!$item) {
            $io->error(sprintf('No s\'ha trobat cap bé amb CUI "%s".', $cui));

            return Command::FAILURE;
        }

        $message = new AnchorBlockchainMessage(
            itemId: (int) $item->getId(),
            actionType: BlockchainActionType::ITEM_REGISTRATION
        );

        if ($sync) {
            $io->info(sprintf('Executant notarització síncrona per al bé "%s" (%s)...', $item->getName(), $cui));
            ($this->handler)($message);

            $receipt = $item->getLatestBlockchainReceipt();
            if ($receipt) {
                $io->success(sprintf(
                    "Notarització completada amb èxit!\n- Tx Hash: %s\n- Bloc Polygon: #%d\n- SHA-256 Ancorat: %s\n- Explorador: %s",
                    $receipt->getTxHash(),
                    $receipt->getBlockNumber(),
                    $receipt->getAnchoredHash(),
                    $receipt->getExplorerUrl()
                ));
            } else {
                $io->warning('El procés ha finalitzat però no s\'ha pogut obtenir el rebut.');
            }
        } else {
            $this->messageBus->dispatch($message);
            $io->success(sprintf(
                'Missatge d\'ancoratge encuat amb èxit a la cua "async_chain" per a %s (%s). Executa "php bin/console messenger:consume async_chain --limit=1" per processar-lo.',
                $item->getName(),
                $cui
            ));
        }

        return Command::SUCCESS;
    }
}
