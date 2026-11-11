<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class OtpVerificationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('code', TextType::class, [
            'label' => 'Codi de verificació (6 dígits)',
            'mapped' => false,
            'attr' => [
                'placeholder' => '000000',
                'maxlength' => 6,
                'inputmode' => 'numeric',
                'pattern' => '[0-9]{6}',
                'autocomplete' => 'one-time-code',
            ],
            'constraints' => [
                new NotBlank([
                    'message' => 'Introdueix el codi de 6 dígits que hem enviat al teu correu.',
                ]),
                new Regex([
                    'pattern' => '/^\s*\d{6}\s*$/',
                    'message' => 'El codi ha d\'estar format exactament per 6 xifres numèriques.',
                ]),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
