<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ChangePasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Contrasenya actual',
                'mapped' => false,
                'attr' => [
                    'placeholder' => 'Contrasenya actual',
                    'autocomplete' => 'current-password',
                    'class' => 'w-full px-3 py-1.5 rounded-lg border border-steel/30 bg-white text-ink text-xs font-raleway focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-colors',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Introdueix la teva contrasenya actual.']),
                    new UserPassword(['message' => 'La contrasenya actual no és correcta.']),
                ],
            ])
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_name' => 'first',
                'second_name' => 'second',
                'invalid_message' => 'Les noves contrasenyes no coincideixen.',
                'first_options' => [
                    'label' => 'Nova contrasenya',
                    'attr' => [
                        'placeholder' => 'Mínim 8 caràcters',
                        'autocomplete' => 'new-password',
                        'class' => 'w-full px-3 py-1.5 rounded-lg border border-steel/30 bg-white text-ink text-xs font-raleway focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-colors',
                    ],
                ],
                'second_options' => [
                    'label' => 'Repeteix nova contrasenya',
                    'attr' => [
                        'placeholder' => 'Repeteix la contrasenya',
                        'autocomplete' => 'new-password',
                        'class' => 'w-full px-3 py-1.5 rounded-lg border border-steel/30 bg-white text-ink text-xs font-raleway focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-colors',
                    ],
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Introdueix la nova contrasenya.']),
                    new Length([
                        'min' => 8,
                        'minMessage' => 'La nova contrasenya ha de tenir com a mínim {{ limit }} caràcters.',
                        'max' => 4096,
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
