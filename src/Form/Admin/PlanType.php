<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Plan;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class PlanType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom Comercial del Pla',
                'constraints' => [new NotBlank()],
                'attr' => [
                    'class' => 'w-full bg-cream-50/60 border border-steel/30 rounded-xl px-3.5 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-gold focus:bg-white',
                ],
            ])
            ->add('priceCents', IntegerType::class, [
                'label' => 'Preu en Cèntims d\'Euro (ex: 2900 = 29,00 €)',
                'attr' => [
                    'class' => 'w-full bg-cream-50/60 border border-steel/30 rounded-xl px-3.5 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-gold focus:bg-white font-mono',
                ],
                'help' => '0 per al Pla Free gratuït.',
            ])
            ->add('billingPeriod', ChoiceType::class, [
                'label' => 'Període de Facturació',
                'choices' => [
                    'Anual (1 any)' => 'yearly',
                    'Mensual (1 mes)' => 'monthly',
                    'Únic / Vitalici' => 'lifetime',
                ],
                'attr' => [
                    'class' => 'w-full bg-cream-50/60 border border-steel/30 rounded-xl px-3.5 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-gold focus:bg-white',
                ],
            ])
            ->add('maxActiveItems', IntegerType::class, [
                'label' => 'Màxim de Béns Actius en Custòdia',
                'attr' => [
                    'class' => 'w-full bg-cream-50/60 border border-steel/30 rounded-xl px-3.5 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-gold focus:bg-white font-mono',
                ],
                'help' => 'Indica -1 per a béns il·limitats.',
            ])
            ->add('maxPhotosPerItem', IntegerType::class, [
                'label' => 'Màxim de Fotografies per Bé Moble',
                'attr' => [
                    'class' => 'w-full bg-cream-50/60 border border-steel/30 rounded-xl px-3.5 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-gold focus:bg-white font-mono',
                ],
                'help' => 'Free: 1, Plus: 5, Unlimited: -1.',
            ])
            ->add('maxEvidencesPerItem', IntegerType::class, [
                'label' => 'Màxim de Proves Documentals / Factures per Bé',
                'attr' => [
                    'class' => 'w-full bg-cream-50/60 border border-steel/30 rounded-xl px-3.5 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-gold focus:bg-white font-mono',
                ],
                'help' => 'Free: 1, Plus: 5, Unlimited: -1.',
            ])
            ->add('aiAssistanceQuota', IntegerType::class, [
                'label' => 'Quota de Consultes d\'Assistència IA Sobirana',
                'attr' => [
                    'class' => 'w-full bg-cream-50/60 border border-steel/30 rounded-xl px-3.5 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-gold focus:bg-white font-mono',
                ],
                'help' => 'Free: 20, Plus: 100, Unlimited: 9999 o -1.',
            ])
            ->add('monthlyCertificatesQuota', IntegerType::class, [
                'label' => 'Quota Mensual de Certificats Oficials',
                'attr' => [
                    'class' => 'w-full bg-cream-50/60 border border-steel/30 rounded-xl px-3.5 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-gold focus:bg-white font-mono',
                ],
            ])
            ->add('allowOfficialCertificates', CheckboxType::class, [
                'label' => 'Permetre Emissió de Certificats Oficials eIDAS / Blockchain',
                'required' => false,
                'attr' => ['class' => 'w-4 h-4 text-ink rounded border-steel/30 focus:ring-gold'],
            ])
            ->add('aiAssistanceEnabled', CheckboxType::class, [
                'label' => 'Assistència IA Sobirana Habilitada per a aquest Pla',
                'required' => false,
                'attr' => ['class' => 'w-4 h-4 text-ink rounded border-steel/30 focus:ring-gold'],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Pla Actiu i Visible a l\'Alta i Canvi de Pla',
                'required' => false,
                'attr' => ['class' => 'w-4 h-4 text-ink rounded border-steel/30 focus:ring-gold'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Plan::class,
        ]);
    }
}
