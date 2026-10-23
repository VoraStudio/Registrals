<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
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
            ->add('taxId', TextType::class, [
                'label' => 'DNI / NIE (Opcional a l\'alta)',
                'required' => false,
                'attr' => [
                    'placeholder' => '12345678X',
                    'autocomplete' => 'off',
                ],
                'constraints' => [
                    new Length(['max' => 20]),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Correu electrònic',
                'attr' => [
                    'placeholder' => 'nom@exemple.cat',
                    'autocomplete' => 'email',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Si us plau, introdueix el teu correu electrònic.']),
                    new Email(['message' => 'L\'adreça de correu no és vàlida.']),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'Les dues contrasenyes han de coincidir.',
                'first_options' => [
                    'label' => 'Contrasenya',
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
                    new NotBlank(['message' => 'Si us plau, introdueix una contrasenya.']),
                    new Length([
                        'min' => 8,
                        'minMessage' => 'La contrasenya ha de tenir com a mínim {{ limit }} caràcters.',
                        'max' => 4096,
                    ]),
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'label' => 'Accepto els termes i condicions legals de servei',
                'constraints' => [
                    new IsTrue([
                        'message' => 'Has d\'acceptar les condicions legals per poder registrar-te.',
                    ]),
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
