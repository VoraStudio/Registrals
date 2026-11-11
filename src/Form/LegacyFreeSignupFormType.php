<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class LegacyFreeSignupFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'placeholder' => 'El teu nom',
                    'autocomplete' => 'given-name',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Si us plau, introdueix el teu nom.']),
                    new Length(['max' => 100]),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Cognoms',
                'attr' => [
                    'placeholder' => 'Els teus cognoms',
                    'autocomplete' => 'family-name',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Si us plau, introdueix els teus cognoms.']),
                    new Length(['max' => 150]),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Les dues contrasenyes han de coincidir.',
                'first_options' => [
                    'label' => 'Contrasenya per al teu compte',
                    'attr' => [
                        'placeholder' => 'Mínim 8 caràcters',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'second_options' => [
                    'label' => 'Repeteix la contrasenya',
                    'attr' => [
                        'placeholder' => 'Torna a escriure la contrasenya',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Si us plau, defineix una contrasenya.']),
                    new Length([
                        'min' => 8,
                        'minMessage' => 'La contrasenya ha de tenir com a mínim {{ limit }} caràcters.',
                        'max' => 4096,
                    ]),
                ],
            ])
            ->add('agreeConsent', CheckboxType::class, [
                'mapped' => false,
                'label' => 'Accepto crear el meu compte gratuït Pla Free i rebre posteriorment les clàusules de custòdia per formalitzar la designació com a Persona de Confiança.',
                'constraints' => [
                    new IsTrue(['message' => 'Cal acceptar la creació del compte per continuar.']),
                ],
            ]);
    }
}
