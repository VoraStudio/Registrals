<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\System\ContactEmailRegistry;
use App\Service\System\WorkerHealthService;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:system:check-workers',
    description: 'Comprova si hi ha cues de Messenger encallades o fallides i envia una alerta per correu.'
)]
final class CheckWorkersCommand extends Command
{
    private const ALERT_COOLDOWN_KEY = 'worker_health_last_alert_ts';

    public function __construct(
        private readonly WorkerHealthService $workerHealthService,
        private readonly MailerInterface $mailer,
        private readonly CacheItemPoolInterface $cache,
        private readonly ContactEmailRegistry $contactEmailRegistry,
        private readonly string $mailerFromAddress = 'info@registrals.com',
        private readonly string $mailerFromName = 'Registrals'
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Adreça de correu destinatària de les alertes')
            ->addOption('cooldown', null, InputOption::VALUE_REQUIRED, 'Minuts mínims entre correus d\'alerta repetits', '60')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Ignora el cooldown i envia el correu encara que ja s\'hagi enviat fa poc');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $report = $this->workerHealthService->getHealthReport();

        if ($report['status'] === 'ok') {
            $io->success(sprintf(
                'Workers OK. Pendents recents: %d | Fallits: 0.',
                $report['totalPending']
            ));

            return Command::SUCCESS;
        }

        $toOpt = $input->getOption('to');
        $to = is_string($toOpt) && trim($toOpt) !== '' ? trim($toOpt) : $this->contactEmailRegistry->get('alertes');
        $cooldownMinutes = max(1, (int) $input->getOption('cooldown'));
        $force = (bool) $input->getOption('force');

        $cooldownItem = $this->cache->getItem(self::ALERT_COOLDOWN_KEY);
        if (!$force && $cooldownItem->isHit()) {
            $io->warning(sprintf(
                'Incidència detectada (fallits: %d, encallades: %s), però el cooldown de %d min encara és actiu.',
                $report['failed'],
                implode(', ', $report['stalledQueues']) ?: 'cap',
                $cooldownMinutes
            ));

            return Command::FAILURE;
        }

        $lines = [
            'Alerta de vigilància dels workers de Registrals (' . $report['checkedAt']->format('d/m/Y H:i:s') . '):',
            '',
            sprintf('- Missatges fallits (failed): %d', $report['failed']),
            sprintf('- Cues encallades (> %d min): %s', WorkerHealthService::STALLED_THRESHOLD_MINUTES, implode(', ', $report['stalledQueues']) ?: 'cap'),
            '',
            'Detall per cua:',
        ];

        foreach ($report['queues'] as $name => $info) {
            $age = $info['oldestAgeMinutes'] !== null ? $info['oldestAgeMinutes'] . ' min' : '-';
            $hb = $info['lastHeartbeatAt'] !== null ? $info['lastHeartbeatAt']->format('H:i:s') : 'sense batec recent';
            $lines[] = sprintf('  * %s: %d pendents (més antic: %s) | heartbeat: %s', $name, $info['count'], $age, $hb);
        }

        $lines[] = '';
        $lines[] = 'Revisa-ho al Backoffice: https://registrals.voradata.cat:8008/admin/serveis';

        $email = (new Email())
            ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
            ->to($to)
            ->subject('[Registrals Alerta] Workers aturats o cua amb incidències')
            ->text(implode("\n", $lines));

        $this->mailer->send($email);

        $cooldownItem->set(time());
        $cooldownItem->expiresAfter($cooldownMinutes * 60);
        $this->cache->save($cooldownItem);

        $io->warning(sprintf('Alerta enviada per correu a %s.', $to));

        return Command::FAILURE;
    }
}

