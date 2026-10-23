<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Plan;
use App\Repository\PlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:init-plans',
    description: 'Initializes default subscription plans (Free, Plus, Unlimited) based on SDD specs',
)]
class InitPlansCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PlanRepository $planRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $plansData = [
            [
                'code' => 'free',
                'name' => 'Pla Free (Custòdia i Inventari)',
                'priceCents' => 0,
                'billingPeriod' => 'yearly',
                'maxActiveItems' => 20,
                'maxLifetimeItems' => 50,
                'monthlyCertificatesQuota' => 0,
                'allowOfficialCertificates' => false,
                'aiAssistanceQuota' => 2,
                'transfersIncluded' => 0,
                'extraTransferPriceCents' => 290,
                'maxDocumentsPerItem' => 4,
            ],
            [
                'code' => 'plus',
                'name' => 'Pla Plus',
                'priceCents' => 2200,
                'billingPeriod' => 'yearly',
                'maxActiveItems' => 100,
                'maxLifetimeItems' => 500,
                'monthlyCertificatesQuota' => 10,
                'allowOfficialCertificates' => true,
                'aiAssistanceQuota' => 50,
                'transfersIncluded' => 5,
                'extraTransferPriceCents' => 0,
                'maxDocumentsPerItem' => 20,
            ],
            [
                'code' => 'unlimited',
                'name' => 'Pla Unlimited / Business',
                'priceCents' => 5900,
                'billingPeriod' => 'yearly',
                'maxActiveItems' => 9999,
                'maxLifetimeItems' => 9999,
                'monthlyCertificatesQuota' => 9999,
                'allowOfficialCertificates' => true,
                'aiAssistanceQuota' => 9999,
                'transfersIncluded' => 9999,
                'extraTransferPriceCents' => 0,
                'maxDocumentsPerItem' => 50,
            ],
        ];

        foreach ($plansData as $data) {
            $plan = $this->planRepository->findOneBy(['code' => $data['code']]);
            if ($plan === null) {
                $plan = new Plan($data['code'], $data['name'], $data['priceCents']);
                $this->entityManager->persist($plan);
                $io->note(sprintf('Creating plan: %s', $data['code']));
            } else {
                $plan->setName($data['name']);
                $plan->setPriceCents($data['priceCents']);
                $io->note(sprintf('Updating plan: %s', $data['code']));
            }

            $plan->setBillingPeriod($data['billingPeriod'])
                ->setMaxActiveItems($data['maxActiveItems'])
                ->setMaxLifetimeItems($data['maxLifetimeItems'])
                ->setMonthlyCertificatesQuota($data['monthlyCertificatesQuota'])
                ->setAllowOfficialCertificates($data['allowOfficialCertificates'])
                ->setAiAssistanceQuota($data['aiAssistanceQuota'])
                ->setTransfersIncluded($data['transfersIncluded'])
                ->setExtraTransferPriceCents($data['extraTransferPriceCents'])
                ->setMaxDocumentsPerItem($data['maxDocumentsPerItem'])
                ->setIsActive(true);
        }

        $this->entityManager->flush();

        $io->success('Default plans initialized successfully.');

        return Command::SUCCESS;
    }
}
