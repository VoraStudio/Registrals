<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\System\WorkerHealthService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class WorkerHealthExtension extends AbstractExtension
{
    public function __construct(
        private readonly WorkerHealthService $workerHealthService
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_worker_health', $this->getWorkerHealth(...)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getWorkerHealth(): array
    {
        return $this->workerHealthService->getHealthReport();
    }
}

