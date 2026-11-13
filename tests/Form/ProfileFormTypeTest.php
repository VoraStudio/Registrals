<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Entity\User;
use App\Form\ProfileFormType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

class ProfileFormTypeTest extends KernelTestCase
{
    public function testIdentityFieldsAreEditableByDefault(): void
    {
        self::bootKernel();
        /** @var FormFactoryInterface $formFactory */
        $formFactory = self::getContainer()->get('form.factory');

        $user = (new User())
            ->setEmail('titular@registrals.cat')
            ->setFirstName('Carles')
            ->setLastName('Mellado Bosch')
            ->setTaxId('12345678Z')
            ->setCountryCode('ES');

        $form = $formFactory->create(ProfileFormType::class, $user, [
            'csrf_protection' => false,
            'validation_groups' => false,
            'lock_identity_fields' => false,
            'lock_tax_id' => false,
        ]);

        self::assertFalse($form->get('firstName')->isDisabled());
        self::assertFalse($form->get('lastName')->isDisabled());
        self::assertFalse($form->get('taxId')->isDisabled());
    }

    public function testIdentityFieldsAndTaxIdAreLockedWhenVerifiedAndTaxIdPresent(): void
    {
        self::bootKernel();
        /** @var FormFactoryInterface $formFactory */
        $formFactory = self::getContainer()->get('form.factory');

        $user = (new User())
            ->setEmail('titular@registrals.cat')
            ->setFirstName('Carles')
            ->setLastName('Mellado Bosch')
            ->setTaxId('12345678Z')
            ->setCountryCode('ES');

        $form = $formFactory->create(ProfileFormType::class, $user, [
            'csrf_protection' => false,
            'validation_groups' => false,
            'lock_identity_fields' => true,
            'lock_tax_id' => true,
        ]);

        self::assertTrue($form->get('firstName')->isDisabled());
        self::assertTrue($form->get('lastName')->isDisabled());
        self::assertTrue($form->get('taxId')->isDisabled());

        $form->submit([
            'firstName' => 'Donald',
            'lastName' => 'Duck',
            'taxId' => '99999999X',
            'phone' => '+34600111222',
            'countryCode' => 'ES',
        ]);

        self::assertTrue($form->isValid());
        self::assertSame('Carles', $user->getFirstName());
        self::assertSame('Mellado Bosch', $user->getLastName());
        self::assertSame('12345678Z', $user->getTaxId());
        self::assertSame('+34600111222', $user->getPhone());
    }

    public function testTaxIdRemainsEditableOnceWhenApprovedWithEmptyTaxId(): void
    {
        self::bootKernel();
        /** @var FormFactoryInterface $formFactory */
        $formFactory = self::getContainer()->get('form.factory');

        $user = (new User())
            ->setEmail('titular@registrals.cat')
            ->setFirstName('Carles')
            ->setLastName('Mellado Bosch')
            ->setTaxId(null)
            ->setCountryCode('ES');

        $form = $formFactory->create(ProfileFormType::class, $user, [
            'csrf_protection' => false,
            'validation_groups' => false,
            'lock_identity_fields' => true,
            'lock_tax_id' => false,
        ]);

        self::assertTrue($form->get('firstName')->isDisabled());
        self::assertTrue($form->get('lastName')->isDisabled());
        self::assertFalse($form->get('taxId')->isDisabled());

        $form->submit([
            'firstName' => 'Donald',
            'lastName' => 'Duck',
            'taxId' => '12345678Z',
            'phone' => '',
            'countryCode' => 'ES',
        ]);

        self::assertTrue($form->isValid());
        self::assertSame('Carles', $user->getFirstName());
        self::assertSame('Mellado Bosch', $user->getLastName());
        self::assertSame('12345678Z', $user->getTaxId());
    }

    public function testBillingFieldsAreEditableAndBindCorrectly(): void
    {
        self::bootKernel();
        /** @var FormFactoryInterface $formFactory */
        $formFactory = self::getContainer()->get('form.factory');

        $user = (new User())
            ->setEmail('titular@registrals.cat')
            ->setFirstName('Carles')
            ->setLastName('Mellado Bosch')
            ->setTaxId('12345678Z')
            ->setCountryCode('ES');

        $form = $formFactory->create(ProfileFormType::class, $user, [
            'csrf_protection' => false,
            'validation_groups' => false,
            'lock_identity_fields' => true,
            'lock_tax_id' => true,
        ]);

        self::assertFalse($form->get('billingName')->isDisabled());
        self::assertFalse($form->get('billingAddress')->isDisabled());
        self::assertFalse($form->get('billingPostalCode')->isDisabled());
        self::assertFalse($form->get('billingCity')->isDisabled());
        self::assertFalse($form->get('billingProvince')->isDisabled());

        $form->submit([
            'phone' => '+34600111222',
            'countryCode' => 'ES',
            'billingName' => 'Empresa Registrals SL',
            'billingAddress' => 'Passeig de Gràcia 100, 3r 1a',
            'billingPostalCode' => '08008',
            'billingCity' => 'Barcelona',
            'billingProvince' => 'Barcelona',
        ]);

        self::assertTrue($form->isValid());
        self::assertSame('Empresa Registrals SL', $user->getBillingName());
        self::assertSame('Empresa Registrals SL', $user->getEffectiveBillingName());
        self::assertSame('Passeig de Gràcia 100, 3r 1a', $user->getBillingAddress());
        self::assertSame('08008', $user->getBillingPostalCode());
        self::assertSame('Barcelona', $user->getBillingCity());
        self::assertSame('Barcelona', $user->getBillingProvince());
        self::assertTrue($user->hasCompleteBillingProfile());
    }
}
