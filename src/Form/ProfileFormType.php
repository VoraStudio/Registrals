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
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'placeholder' => 'El teu nom',
                    'autocomplete' => 'given-name',
                    'class' => 'w-full px-3 py-1.5 rounded-lg border border-steel/30 bg-white text-ink text-xs font-raleway focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-colors',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'El nom és obligatori.']),
                    new Length(['max' => 100]),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Cognoms',
                'attr' => [
                    'placeholder' => 'Els teus cognoms',
                    'autocomplete' => 'family-name',
                    'class' => 'w-full px-3 py-1.5 rounded-lg border border-steel/30 bg-white text-ink text-xs font-raleway focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-colors',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Els cognoms són obligatoris.']),
                    new Length(['max' => 150]),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Correu electrònic',
                'disabled' => true,
                'attr' => [
                    'class' => 'w-full px-3 py-1.5 rounded-lg border border-steel/20 bg-cream/30 text-ink/70 text-xs font-raleway cursor-not-allowed',
                ],
            ])
            ->add('taxId', TextType::class, [
                'label' => 'NIF / NIE / CIF Fiscal',
                'required' => false,
                'attr' => [
                    'placeholder' => 'ex: 12345678Z o B12345678',
                    'autocomplete' => 'off',
                    'class' => 'w-full px-3 py-1.5 rounded-lg border border-steel/30 bg-white text-ink text-xs font-raleway uppercase focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-colors',
                ],
                'constraints' => [
                    new Length(['max' => 20]),
                ],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Telèfon',
                'required' => false,
                'attr' => [
                    'placeholder' => '+34 600 000 000',
                    'autocomplete' => 'tel',
                    'class' => 'w-full px-3 py-1.5 rounded-lg border border-steel/30 bg-white text-ink text-xs font-raleway focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-colors',
                ],
                'constraints' => [
                    new Length(['max' => 30]),
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
                    'class' => 'w-full px-3 py-1.5 rounded-lg border border-steel/30 bg-white text-ink text-xs font-raleway focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-colors',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
