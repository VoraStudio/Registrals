<?php

declare(strict_types=1);

namespace App\Service\System;

use DateTimeImmutable;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\CacheItemPoolInterface;
use Throwable;

/**
 * Servei compartit de vigilància dels workers de Symfony Messenger (cues encallades, fallits i heartbeat).
 */
final class WorkerHealthService
{
    public const STALLED_THRESHOLD_MINUTES = 5;
    public const HEARTBEAT_STALE_SECONDS = 120;
    public const MONITORED_QUEUES = ['async_chain', 'async_ai', 'async'];

    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly CacheItemPoolInterface $cache
    ) {
    }

    public function recordHeartbeat(string $transportName): void
    {
        try {
            $item = $this->cache->getItem($this->heartbeatCacheKey($transportName));
            $item->set((new DateTimeImmutable())->format(DATE_ATOM));
            $item->expiresAfter(3600);
            $this->cache->save($item);
        } catch (Throwable) {
        }
    }

    /**
     * @return array{
     *     status: 'ok'|'critical',
     *     failed: int,
     *     totalPending: int,
     *     stalledQueues: string[],
     *     queues: array<string, array{
     *         count: int,
     *         oldestCreatedAt: ?DateTimeImmutable,
     *         oldestAgeMinutes: ?int,
     *         isStalled: bool,
     *         lastHeartbeatAt: ?DateTimeImmutable,
     *         workerAlive: bool
     *     }>,
     *     checkedAt: DateTimeImmutable
     * }
     */
    public function getHealthReport(): array
    {
        $now = new DateTimeImmutable();
        $queues = [];
        foreach (self::MONITORED_QUEUES as $q) {
            $heartbeat = $this->readHeartbeat($q);
            $queues[$q] = [
                'count' => 0,
                'oldestCreatedAt' => null,
                'oldestAgeMinutes' => null,
                'isStalled' => false,
                'lastHeartbeatAt' => $heartbeat,
                'workerAlive' => $heartbeat !== null && ($now->getTimestamp() - $heartbeat->getTimestamp()) <= self::HEARTBEAT_STALE_SECONDS,
            ];
        }

        $failed = 0;
        $totalPending = 0;
        $stalledQueues = [];

        try {
            $conn = $this->doctrine->getConnection('default');
            $rows = $conn->executeQuery(
                'SELECT queue_name, COUNT(*) AS cnt, MIN(created_at) AS oldest_created_at FROM messenger_messages WHERE delivered_at IS NULL GROUP BY queue_name'
            )->fetchAllAssociative();

            foreach ($rows as $row) {
                $q = (string) $row['queue_name'];
                $cnt = (int) $row['cnt'];

                if ($q === 'failed') {
                    $failed = $cnt;
                    continue;
                }

                $totalPending += $cnt;
                $oldest = !empty($row['oldest_created_at']) ? new DateTimeImmutable((string) $row['oldest_created_at']) : null;
                $ageMinutes = $oldest !== null ? max(0, (int) floor(($now->getTimestamp() - $oldest->getTimestamp()) / 60)) : null;
                $isStalled = $cnt > 0 && $ageMinutes !== null && $ageMinutes >= self::STALLED_THRESHOLD_MINUTES;

                if (!isset($queues[$q])) {
                    $heartbeat = $this->readHeartbeat($q);
                    $queues[$q] = [
                        'count' => 0,
                        'oldestCreatedAt' => null,
                        'oldestAgeMinutes' => null,
                        'isStalled' => false,
                        'lastHeartbeatAt' => $heartbeat,
                        'workerAlive' => $heartbeat !== null && ($now->getTimestamp() - $heartbeat->getTimestamp()) <= self::HEARTBEAT_STALE_SECONDS,
                    ];
                }

                $queues[$q]['count'] = $cnt;
                $queues[$q]['oldestCreatedAt'] = $oldest;
                $queues[$q]['oldestAgeMinutes'] = $ageMinutes;
                $queues[$q]['isStalled'] = $isStalled;

                if ($isStalled) {
                    $stalledQueues[] = $q;
                }
            }
        } catch (Throwable) {
        }

        $status = ($failed > 0 || $stalledQueues !== []) ? 'critical' : 'ok';

        return [
            'status' => $status,
            'failed' => $failed,
            'totalPending' => $totalPending,
            'stalledQueues' => $stalledQueues,
            'queues' => $queues,
            'checkedAt' => $now,
        ];
    }

    private function readHeartbeat(string $transportName): ?DateTimeImmutable
    {
        try {
            $item = $this->cache->getItem($this->heartbeatCacheKey($transportName));
            if ($item->isHit() && is_string($item->get())) {
                return new DateTimeImmutable($item->get());
            }
        } catch (Throwable) {
        }

        return null;
    }

    private function heartbeatCacheKey(string $transportName): string
    {
        return 'worker_heartbeat_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $transportName);
    }
}
