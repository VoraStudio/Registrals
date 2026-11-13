<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Infrastructure\Adapter\Identity\IdentityVerificationGatewayInterface;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Eina de diagnòstic per crear o consultar una sessió eIDV real contra Namirial OnBoarding API v2.
 */
#[AsCommand(
    name: 'app:kyc:test',
    description: 'Crea una sessió de prova a Namirial OnBoarding API v2 o consulta el veredicte d\'una sol·licitud existent',
)]
class NamirialKycTestCommand extends Command
{
    private const DEFAULT_BACK_URL = 'https://registrals.voradata.cat:8008/app/perfil?requestId=<requestId>&referenceId=<referenceId>&status=<status>&actor=<actor>';

    public function __construct(
        private readonly IdentityVerificationGatewayInterface $identityGateway,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('user', null, InputOption::VALUE_REQUIRED, 'Email o ID d\'un usuari existent de Registrals')
            ->addOption('first-name', null, InputOption::VALUE_REQUIRED, 'Nom del titular a contrastar al DNI')
            ->addOption('last-name', null, InputOption::VALUE_REQUIRED, 'Cognoms del titular a contrastar al DNI')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Correu electrònic de la prova')
            ->addOption('back-url', null, InputOption::VALUE_REQUIRED, 'URL de retorn (wizardUrlBack)', self::DEFAULT_BACK_URL)
            ->addOption('check', null, InputOption::VALUE_REQUIRED, 'Consulta l\'estat i veredicte JSON real d\'un requestId ja creat')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $checkRequestId = trim((string) ($input->getOption('check') ?? ''));
        if ($checkRequestId !== '') {
            return $this->checkExistingRequest($io, $checkRequestId);
        }

        $targetUser = $this->resolveUser($input, $io);
        if ($targetUser === null) {
            return Command::FAILURE;
        }

        $backUrl = trim((string) ($input->getOption('back-url') ?? self::DEFAULT_BACK_URL));
        $referenceId = sprintf('REG-TEST-%d-%s', $targetUser->getId() ?? 0, strtoupper(bin2hex(random_bytes(4))));

        $io->title('Namirial OnBoarding API v2 · Sessió de Prova eIDV');
        $io->definitionList(
            ['Mode Live' => $this->identityGateway->isLiveConfigured() ? 'ACTIU (Demo / API v2 real)' : 'SIMULACIÓ (sense API key configurada)'],
            ['Nom enviat' => $targetUser->getFirstName()],
            ['Cognoms enviats' => $targetUser->getLastName()],
            ['Correu' => $targetUser->getEmail()],
            ['Referència' => $referenceId],
            ['wizardUrlBack' => $backUrl],
        );

        try {
            $session = $this->identityGateway->startVerificationSession($targetUser, $referenceId, $backUrl);
        } catch (\Throwable $e) {
            $io->error('Error en iniciar la sessió Namirial OnBoarding: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->success('Sessió eIDV generada correctament.');
        $io->table(
            ['Camp', 'Valor'],
            [
                ['requestId', $session->requestId],
                ['referenceId', $session->referenceId],
                ['isSimulated', $session->isSimulated ? 'Sí' : 'No (Real)'],
                ['wizardUrl', $session->wizardUrl],
            ]
        );

        if ($session->rawResponse !== null) {
            $io->section('Resposta bruta POST /requests');
            $decoded = json_decode($session->rawResponse, true);
            $io->writeln(json_encode($decoded ?? $session->rawResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $session->rawResponse);
        }

        $io->note('Un cop completat el procés al navegador/mòbil, consulta el resultat real amb:' . PHP_EOL . sprintf('php bin/console app:kyc:test --check=%s', $session->requestId));

        return Command::SUCCESS;
    }

    private function checkExistingRequest(SymfonyStyle $io, string $requestId): int
    {
        $io->title('Namirial OnBoarding API v2 · Consulta de Veredicte');

        try {
            $verdict = $this->identityGateway->fetchVerificationVerdict($requestId);
        } catch (\Throwable $e) {
            $io->error('Error en consultar la sol·licitud: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->table(
            ['Camp', 'Valor'],
            [
                ['requestId', $verdict->requestId],
                ['referenceId', $verdict->referenceId ?? '—'],
                ['status intern', strtoupper($verdict->status->value)],
                ['providerStatus', $verdict->providerStatus],
                ['reason', $verdict->reason ?? '—'],
                ['verifiedFirstName', $verdict->verifiedFirstName ?? '—'],
                ['verifiedLastName', $verdict->verifiedLastName ?? '—'],
                ['biometricMatchScore', $verdict->biometricMatchScore !== null ? (string) $verdict->biometricMatchScore : '—'],
                ['isSimulated', $verdict->isSimulated ? 'Sí' : 'No (Real)'],
            ]
        );

        if ($verdict->rawPayload !== []) {
            $io->section('Payload JSON complet rebut de Namirial');
            $io->writeln(json_encode($verdict->rawPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
        }

        return Command::SUCCESS;
    }

    private function resolveUser(InputInterface $input, SymfonyStyle $io): ?User
    {
        $userSelector = trim((string) ($input->getOption('user') ?? ''));
        $firstName = trim((string) ($input->getOption('first-name') ?? ''));
        $lastName = trim((string) ($input->getOption('last-name') ?? ''));
        $email = trim((string) ($input->getOption('email') ?? ''));

        if ($userSelector !== '') {
            $found = ctype_digit($userSelector)
                ? $this->userRepository->find((int) $userSelector)
                : $this->userRepository->findOneBy(['email' => $userSelector]);

            if ($found === null) {
                $io->error(sprintf('No s\'ha trobat cap usuari amb selector "%s".', $userSelector));
                return null;
            }

            if ($firstName !== '' || $lastName !== '' || $email !== '') {
                $clone = (new User())
                    ->setFirstName($firstName !== '' ? $firstName : $found->getFirstName())
                    ->setLastName($lastName !== '' ? $lastName : $found->getLastName())
                    ->setEmail($email !== '' ? $email : $found->getEmail())
                    ->setPhone($found->getPhone());

                return $clone;
            }

            return $found;
        }

        if ($firstName === '' || $lastName === '' || $email === '') {
            $io->error('Cal indicar --user=<email|id> o bé --first-name="..." --last-name="..." --email="..."');
            return null;
        }

        return (new User())
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setEmail($email);
    }
}

