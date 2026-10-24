<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Item;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Control d'accés estricte per a la gestió de béns (Item).
 * Assegura que només el propietari legítim o un administrador pugui veure o editar un bé.
 */
class ItemVoter extends Voter
{
    public const VIEW = 'ITEM_VIEW';
    public const EDIT = 'ITEM_EDIT';
    public const DELETE = 'ITEM_DELETE';
    public const TRANSFER = 'ITEM_TRANSFER';
    public const CERTIFY = 'ITEM_CERTIFY';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::TRANSFER, self::CERTIFY], true)
            && $subject instanceof Item;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // L'administrador té accés complet de gestió
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        /** @var Item $item */
        $item = $subject;

        return match ($attribute) {
            self::VIEW, self::CERTIFY => $this->canViewOrCertify($item, $user),
            self::EDIT, self::DELETE => $this->canEditOrDelete($item, $user),
            self::TRANSFER => $this->canTransfer($item, $user),
            default => false,
        };
    }

    private function canViewOrCertify(Item $item, User $user): bool
    {
        // El propietari sempre pot veure i certificar el seu bé
        return $item->getOwner()->getId() === $user->getId();
    }

    private function canEditOrDelete(Item $item, User $user): bool
    {
        // Només el propietari pot editar o donar de baixa, sempre que no estigui en traspàs o ja traspassat
        if ($item->getOwner()->getId() !== $user->getId()) {
            return false;
        }

        return !in_array($item->getStatus(), [
            \App\Enum\ItemStatus::TRANSFER_PENDING,
            \App\Enum\ItemStatus::TRANSFERRED,
        ], true);
    }

    private function canTransfer(Item $item, User $user): bool
    {
        // Només el propietari d'un bé actiu pot iniciar un traspàs
        if ($item->getOwner()->getId() !== $user->getId()) {
            return false;
        }

        return $item->getStatus() === \App\Enum\ItemStatus::ACTIVE;
    }
}
