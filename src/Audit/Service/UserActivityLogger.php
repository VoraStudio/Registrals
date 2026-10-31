<?php

declare(strict_types=1);

namespace App\Audit\Service;

use App\Audit\Entity\UserActivityLog;
use App\Audit\Enum\UserActivityAction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

class UserActivityLogger
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.audit_entity_manager')]
        private readonly EntityManagerInterface $auditEntityManager,
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * @param array<string, mixed>|null $details
     */
    public function log(
        User $user,
        UserActivityAction $action,
        string $description,
        ?array $details = null
    ): UserActivityLog {
        $request = $this->requestStack->getCurrentRequest();
        $ipAddress = $request?->getClientIp();
        $userAgent = $request?->headers->get('User-Agent');

        $log = new UserActivityLog(
            userUuid: $user->getUuid()->toRfc4122(),
            userEmail: $user->getEmail(),
            userName: $user->getFullName(),
            action: $action,
            description: $description,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            details: $details
        );

        $this->auditEntityManager->persist($log);
        $this->auditEntityManager->flush();

        return $log;
    }

    /**
     * @param array<string, mixed>|null $details
     */
    public function logAnonymous(
        string $identifier,
        UserActivityAction $action,
        string $description,
        ?array $details = null,
        ?string $userName = 'Intent no autenticat'
    ): UserActivityLog {
        $request = $this->requestStack->getCurrentRequest();
        $ipAddress = $request?->getClientIp();
        $userAgent = $request?->headers->get('User-Agent');

        $log = new UserActivityLog(
            userUuid: '00000000-0000-0000-0000-000000000000',
            userEmail: $identifier,
            userName: $userName ?? 'Intent no autenticat',
            action: $action,
            description: $description,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            details: $details
        );

        $this->auditEntityManager->persist($log);
        $this->auditEntityManager->flush();

        return $log;
    }
}
