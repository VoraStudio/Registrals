<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ResetPasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('newPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'mapped' => false,
            'first_name' => 'first',
            'second_name' => 'second',
            'invalid_message' => 'Les dues contrasenyes han de coincidir.',
            'first_options' => [
                'label' => 'Nova contrasenya',
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
                new NotBlank(['message' => 'Si us plau, introdueix una nova contrasenya.']),
                new Length([
                    'min' => 8,
                    'minMessage' => 'La contrasenya ha de tenir com a mínim {{ limit }} caràcters.',
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
