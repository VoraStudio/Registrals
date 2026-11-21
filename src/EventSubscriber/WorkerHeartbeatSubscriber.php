<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\System\WorkerHealthService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;

/**
 * Enregistra el batec (heartbeat) dels workers de Messenger actius sense impactar la base de dades.
 */
final class WorkerHeartbeatSubscriber implements EventSubscriberInterface
{
    /** @var array<string, int> */
    private array $lastRecordedAt = [];

    public function __construct(
        private readonly WorkerHealthService $workerHealthService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerRunningEvent::class => 'onWorkerRunning',
        ];
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        $now = time();
        foreach ($event->getWorker()->getMetadata()->getTransportNames() as $transport) {
            if (isset($this->lastRecordedAt[$transport]) && ($now - $this->lastRecordedAt[$transport]) < 30) {
                continue;
            }

            $this->workerHealthService->recordHeartbeat($transport);
            $this->lastRecordedAt[$transport] = $now;
        }
    }
}

