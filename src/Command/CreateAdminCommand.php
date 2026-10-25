<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\PlanRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:create-admin',
    description: 'Crea un usuari administrador dedicat amb ROLE_ADMIN'
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PlanRepository $planRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Correu electrònic de l\'administrador')
            ->addArgument('password', InputArgument::REQUIRED, 'Contrasenya de l\'administrador')
            ->addArgument('firstName', InputArgument::OPTIONAL, 'Nom', 'Administrador')
            ->addArgument('lastName', InputArgument::OPTIONAL, 'Cognom', 'Registrals');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');
        $password = (string) $input->getArgument('password');
        $firstName = (string) $input->getArgument('firstName');
        $lastName = (string) $input->getArgument('lastName');

        $existing = $this->userRepository->findOneBy(['email' => $email]);
        if ($existing) {
            $io->error(sprintf('Ja existeix un compte amb l\'email %s.', $email));
            return Command::FAILURE;
        }

        $plan = $this->planRepository->findOneBy(['code' => 'unlimited']) ?? $this->planRepository->findDefaultFreePlan();
        if (!$plan) {
            $io->error('No s\'ha trobat cap pla configurat a la base de dades.');
            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setPlan($plan);
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
        $user->setIsActive(true);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('Usuari administrador creat correctament: %s (amb ROLE_ADMIN i pla %s).', $email, $plan->getName()));

        return Command::SUCCESS;
    }
}
