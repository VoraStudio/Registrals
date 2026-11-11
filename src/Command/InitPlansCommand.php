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
                'maxYearlyRegistrations' => 20,
                'maxLifetimeItems' => 50,
                'monthlyCertificatesQuota' => 0,
                'extraCertificatePriceCents' => 590,
                'allowOfficialCertificates' => false,
                'kycIncluded' => 0,
                'kycPriceCents' => 350,
                'aiAssistanceQuota' => 20,
                'transfersIncluded' => 0,
                'extraTransferPriceCents' => 290,
                'maxDocumentsPerItem' => 2,
                'maxPhotosPerItem' => 1,
                'maxEvidencesPerItem' => 1,
            ],
            [
                'code' => 'plus',
                'name' => 'Pla Plus',
                'priceCents' => 2200,
                'billingPeriod' => 'yearly',
                'maxActiveItems' => 100,
                'maxYearlyRegistrations' => 120,
                'maxLifetimeItems' => 500,
                'monthlyCertificatesQuota' => 10,
                'extraCertificatePriceCents' => 590,
                'allowOfficialCertificates' => true,
                'kycIncluded' => 1,
                'kycPriceCents' => 350,
                'aiAssistanceQuota' => 100,
                'transfersIncluded' => 5,
                'extraTransferPriceCents' => 0,
                'maxDocumentsPerItem' => 10,
                'maxPhotosPerItem' => 5,
                'maxEvidencesPerItem' => 5,
            ],
            [
                'code' => 'unlimited',
                'name' => 'Pla Unlimited / Business',
                'priceCents' => 5900,
                'billingPeriod' => 'yearly',
                'maxActiveItems' => 9999,
                'maxYearlyRegistrations' => 9999,
                'maxLifetimeItems' => 9999,
                'monthlyCertificatesQuota' => 9999,
                'extraCertificatePriceCents' => 0,
                'allowOfficialCertificates' => true,
                'kycIncluded' => 9999,
                'kycPriceCents' => 0,
                'aiAssistanceQuota' => 9999,
                'transfersIncluded' => 9999,
                'extraTransferPriceCents' => 0,
                'maxDocumentsPerItem' => 9999,
                'maxPhotosPerItem' => 9999,
                'maxEvidencesPerItem' => 9999,
                'isActive' => false,
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
                ->setMaxYearlyRegistrations($data['maxYearlyRegistrations'])
                ->setMaxLifetimeItems($data['maxLifetimeItems'])
                ->setMonthlyCertificatesQuota($data['monthlyCertificatesQuota'])
                ->setExtraCertificatePriceCents($data['extraCertificatePriceCents'])
                ->setAllowOfficialCertificates($data['allowOfficialCertificates'])
                ->setKycIncluded($data['kycIncluded'])
                ->setKycPriceCents($data['kycPriceCents'])
                ->setAiAssistanceQuota($data['aiAssistanceQuota'])
                ->setTransfersIncluded($data['transfersIncluded'])
                ->setExtraTransferPriceCents($data['extraTransferPriceCents'])
                ->setMaxDocumentsPerItem($data['maxDocumentsPerItem'])
                ->setMaxPhotosPerItem($data['maxPhotosPerItem'])
                ->setMaxEvidencesPerItem($data['maxEvidencesPerItem'])
                ->setIsActive($data['isActive'] ?? true);
        }

        $this->entityManager->flush();

        $io->success('Default plans initialized successfully.');

        return Command::SUCCESS;
    }
}
