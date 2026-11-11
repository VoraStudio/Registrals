<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\AnalyzeSuccessionClaimMessage;
use App\Repository\SuccessionClaimRepository;
use App\Service\Legacy\SuccessionClaimWorkflowService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AnalyzeSuccessionClaimMessageHandler
{
    public function __construct(
        private SuccessionClaimRepository $claimRepository,
        private SuccessionClaimWorkflowService $workflowService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(AnalyzeSuccessionClaimMessage $message): void
    {
        $claim = $this->claimRepository->find($message->claimId);
        if ($claim === null) {
            $this->logger->warning('Expedient successori no trobat per a pre-triatge IA', [
                'claim_id' => $message->claimId,
            ]);

            return;
        }

        $this->workflowService->runAiPrescreening($claim);
    }
}

