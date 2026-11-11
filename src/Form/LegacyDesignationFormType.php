<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class LegacyDesignationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $inputClasses = 'w-full px-3 py-2 bg-cream-50/50 border border-steel/30 rounded-lg text-ink text-xs sm:text-sm font-raleway focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold focus:bg-white transition-all';

        $builder
            ->add('fullName', TextType::class, [
                'label' => 'Nom i Cognoms de la Persona de Confiança',
                'attr' => [
                    'placeholder' => 'Ex: Maria Puig Serra',
                    'class' => $inputClasses,
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Introdueix el nom i cognoms de la persona designada.']),
                    new Length(['max' => 180]),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Correu Electrònic de Contacte',
                'attr' => [
                    'placeholder' => 'maria@exemple.cat',
                    'class' => $inputClasses,
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Introdueix un correu electrònic vàlid.']),
                    new Email(['message' => 'El format del correu electrònic no és correcte.']),
                    new Length(['max' => 180]),
                ],
            ])
            ->add('relationship', TextType::class, [
                'label' => 'Vincle / Relació (Opcional)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: Cònjuge, Filla, Marmessor, Assessor',
                    'class' => $inputClasses,
                ],
                'constraints' => [
                    new Length(['max' => 80]),
                ],
            ])
            ->add('ownerConsent', CheckboxType::class, [
                'mapped' => false,
                'label' => 'Designo expressament aquesta persona conforme a l\'Art. 96 LOPDGDD i autoritzo Registrals a enviar-li l\'avís de transparència (Art. 14 RGPD) sense divulgar cap dada patrimonial dels meus béns.',
                'constraints' => [
                    new IsTrue(['message' => 'Cal marcar el consentiment exprés per enviar la invitació.']),
                ],
            ]);
    }
}
