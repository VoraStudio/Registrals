<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class ProfileFormType extends AbstractType
{
    private const INPUT_CLASS = 'w-full px-3 py-1.5 rounded-lg border border-steel/30 bg-white text-ink text-xs font-raleway focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-colors';
    private const DISABLED_INPUT_CLASS = 'w-full px-3 py-1.5 rounded-lg border border-steel/20 bg-cream/30 text-ink/70 text-xs font-raleway cursor-not-allowed';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $lockIdentity = (bool) $options['lock_identity_fields'];
        $lockTaxId = (bool) $options['lock_tax_id'];

        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Nom',
                'disabled' => $lockIdentity,
                'attr' => [
                    'placeholder' => 'El teu nom',
                    'autocomplete' => 'given-name',
                    'class' => $lockIdentity ? self::DISABLED_INPUT_CLASS : self::INPUT_CLASS,
                ],
                'constraints' => [
                    new NotBlank(message: 'El nom és obligatori.'),
                    new Length(max: 100),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Cognoms',
                'disabled' => $lockIdentity,
                'attr' => [
                    'placeholder' => 'Els teus cognoms',
                    'autocomplete' => 'family-name',
                    'class' => $lockIdentity ? self::DISABLED_INPUT_CLASS : self::INPUT_CLASS,
                ],
                'constraints' => [
                    new NotBlank(message: 'Els cognoms són obligatoris.'),
                    new Length(max: 150),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Correu electrònic',
                'disabled' => true,
                'attr' => [
                    'class' => self::DISABLED_INPUT_CLASS,
                ],
            ])
            ->add('taxId', TextType::class, [
                'label' => 'NIF / NIE / CIF Fiscal',
                'required' => false,
                'disabled' => $lockTaxId,
                'attr' => [
                    'placeholder' => 'ex: 12345678Z o B12345678',
                    'autocomplete' => 'off',
                    'class' => ($lockTaxId ? self::DISABLED_INPUT_CLASS : self::INPUT_CLASS) . ' uppercase',
                ],
                'constraints' => [
                    new Length(max: 20),
                ],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Telèfon',
                'required' => false,
                'attr' => [
                    'placeholder' => '+34 600 000 000',
                    'autocomplete' => 'tel',
                    'class' => self::INPUT_CLASS,
                ],
                'constraints' => [
                    new Length(max: 30),
                ],
            ])
            ->add('countryCode', ChoiceType::class, [
                'label' => 'País Fiscal',
                'choices' => [
                    'Espanya (ES)' => 'ES',
                    'Andorra (AD)' => 'AD',
                    'França (FR)' => 'FR',
                    'Portugal (PT)' => 'PT',
                    'Itàlia (IT)' => 'IT',
                    'Alemanya (DE)' => 'DE',
                ],
                'attr' => [
                    'class' => self::INPUT_CLASS,
                ],
            ])
            ->add('billingName', TextType::class, [
                'label' => 'Raó Social / Nom de Facturació',
                'required' => false,
                'attr' => [
                    'placeholder' => 'ex: Registrals SL (si difereix del nom personal)',
                    'autocomplete' => 'organization',
                    'class' => self::INPUT_CLASS,
                ],
                'constraints' => [
                    new Length(max: 255),
                ],
            ])
            ->add('billingAddress', TextType::class, [
                'label' => 'Adreça Fiscal',
                'required' => false,
                'attr' => [
                    'placeholder' => 'ex: Carrer Major 12, 1r 2a',
                    'autocomplete' => 'street-address',
                    'class' => self::INPUT_CLASS,
                ],
                'constraints' => [
                    new Length(max: 255),
                ],
            ])
            ->add('billingPostalCode', TextType::class, [
                'label' => 'Codi Postal',
                'required' => false,
                'attr' => [
                    'placeholder' => 'ex: 08001',
                    'autocomplete' => 'postal-code',
                    'class' => self::INPUT_CLASS,
                ],
                'constraints' => [
                    new Length(max: 20),
                ],
            ])
            ->add('billingCity', TextType::class, [
                'label' => 'Població / Ciutat',
                'required' => false,
                'attr' => [
                    'placeholder' => 'ex: Barcelona',
                    'autocomplete' => 'address-level2',
                    'class' => self::INPUT_CLASS,
                ],
                'constraints' => [
                    new Length(max: 100),
                ],
            ])
            ->add('billingProvince', TextType::class, [
                'label' => 'Província / Estat',
                'required' => false,
                'attr' => [
                    'placeholder' => 'ex: Barcelona',
                    'autocomplete' => 'address-level1',
                    'class' => self::INPUT_CLASS,
                ],
                'constraints' => [
                    new Length(max: 100),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'lock_identity_fields' => false,
            'lock_tax_id' => false,
        ]);

        $resolver->setAllowedTypes('lock_identity_fields', 'bool');
        $resolver->setAllowedTypes('lock_tax_id', 'bool');
    }
}
