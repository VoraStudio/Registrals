<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\LegacyContact;
use App\Entity\User;
use App\Enum\LegacyContactStatus;
use PHPUnit\Framework\TestCase;

class LegacyContactTest extends TestCase
{
    public function testLifecycleTransitionsAndExpiration(): void
    {
        $owner = (new User())->setEmail('titular@registrals.cat')->setFirstName('Anna')->setLastName('Puig');
        $beneficiary = (new User())->setEmail('maria@exemple.cat')->setFirstName('Maria')->setLastName('Puig');

        $contact = new LegacyContact(
            owner: $owner,
            fullName: 'Maria Puig',
            email: 'Maria.Puig@Exemple.CAT ',
            relationship: 'Germana',
            ownerConsentIp: '127.0.0.1'
        );

        $this->assertSame('maria.puig@exemple.cat', $contact->getEmail());
        $this->assertSame(LegacyContactStatus::PENDING, $contact->getStatus());
        $this->assertTrue($contact->getStatus()->isPending());
        $this->assertTrue($contact->isInvitationExpired());

        $tokenHash = hash('sha256', 'raw-token-123');
        $contact->issueInvitationToken($tokenHash, new \DateTimeImmutable('+14 days'));
        $this->assertSame($tokenHash, $contact->getInvitationTokenHash());
        $this->assertFalse($contact->isInvitationExpired());

        $contact->accept($beneficiary, '192.168.1.10');
        $this->assertSame(LegacyContactStatus::ACCEPTED, $contact->getStatus());
        $this->assertTrue($contact->getStatus()->isAccepted());
        $this->assertSame($beneficiary, $contact->getBeneficiaryUser());
        $this->assertNotNull($contact->getContactConsentedAt());
        $this->assertNull($contact->getInvitationTokenHash());

        $contact->revoke();
        $this->assertSame(LegacyContactStatus::REVOKED, $contact->getStatus());
        $this->assertTrue($contact->getStatus()->isTerminated());
        $this->assertNotNull($contact->getRevokedAt());
    }

    public function testDeclineClearsPendingToken(): void
    {
        $owner = (new User())->setEmail('titular@registrals.cat')->setFirstName('Anna')->setLastName('Puig');
        $contact = new LegacyContact($owner, 'Joan Serra', 'joan@exemple.cat');
        $contact->issueInvitationToken(hash('sha256', 'secret'), new \DateTimeImmutable('+7 days'));

        $contact->decline('10.0.0.5');

        $this->assertSame(LegacyContactStatus::DECLINED, $contact->getStatus());
        $this->assertTrue($contact->getStatus()->isTerminated());
        $this->assertNull($contact->getInvitationTokenHash());
        $this->assertSame('10.0.0.5', $contact->getContactConsentIp());
    }
}
