<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\GeoIp\GeoIpDatabaseManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:geoip:update',
    description: 'Actualitza o gestiona la base de dades local de GeoIP (MaxMind GeoLite2-Country)',
)]
class UpdateGeoIpDatabaseCommand extends Command
{
    public function __construct(
        private readonly GeoIpDatabaseManager $dbManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Força la descàrrega encara que la base tingui menys de 30 dies')
            ->addOption('key', 'k', InputOption::VALUE_REQUIRED, 'Clau de llicència de MaxMind (si no està configurada a MAXMIND_LICENSE_KEY)')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Importa directament un fitxer GeoLite2-Country.mmdb local')
            ->addOption('status', 's', InputOption::VALUE_NONE, 'Mostra únicament l\'estat actual de la base de dades sense descarregar')
            ->addOption('mirror', 'm', InputOption::VALUE_NONE, 'Utilitza el mirall públic de GeoLite2 si no hi ha MAXMIND_LICENSE_KEY configurada')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Gestió de la Base de Dades GeoIP (MaxMind)');

        $status = $this->dbManager->getDatabaseStatus();

        // 1. Mostrar estat actual
        $io->section('Estat actual');
        $io->definitionList(
            ['Fitxer' => $status['path']],
            ['Existeix' => $status['exists'] ? 'SÍ' : 'NO (s\'utilitza fallback local de rangs)'],
            ['Mida' => $status['size_formatted'] ?? 'N/A'],
            ['Última actualització' => $status['updated_at'] ? $status['updated_at']->format('Y-m-d H:i:s') . " (fa {$status['age_days']} dies)" : 'Mai'],
            ['Estat' => $status['is_stale'] ? 'OBSOLETA o INEXISTENT (>30 dies)' : 'ACTUALITZADA (<30 dies)'],
            ['Clau MaxMind (.env)' => $status['has_license_key'] ? 'CONFIGURADA' : 'NO CONFIGURADA'],
        );

        if ($input->getOption('status')) {
            return Command::SUCCESS;
        }

        // 2. Importació manual des de fitxer local
        $sourceFile = $input->getOption('file');
        if ($sourceFile !== null) {
            $io->section('Importació manual');
            $res = $this->dbManager->importDatabaseFile((string) $sourceFile);
            if ($res['success']) {
                $io->success($res['message']);

                return Command::SUCCESS;
            }
            $io->error($res['message']);

            return Command::FAILURE;
        }

        // 3. Descàrrega automàtica des de MaxMind
        $force = (bool) $input->getOption('force');
        $key = $input->getOption('key');
        $allowMirror = (bool) $input->getOption('mirror') || ($key === null && !$status['has_license_key']);

        $io->section('Actualització des de MaxMind');
        $io->comment('Descarregant i verificant el paquet GeoLite2-Country...');

        $res = $this->dbManager->updateDatabase($key ? (string) $key : null, $force, $allowMirror);

        if ($res['success']) {
            $io->success($res['message']);

            return Command::SUCCESS;
        }

        $io->warning($res['message']);
        $io->note([
            'Com obtenir una clau gratuïta:',
            '1. Registra\'t de franc a https://www.maxmind.com/en/geolite2/signup',
            '2. Genera una License Key a "Manage License Keys"',
            '3. Afegeix-la al teu fitxer .env: MAXMIND_LICENSE_KEY=la_teva_clau',
            '4. O passa-la a la comanda: php bin/console app:geoip:update --key=la_teva_clau',
        ]);

        return Command::FAILURE;
    }
}
