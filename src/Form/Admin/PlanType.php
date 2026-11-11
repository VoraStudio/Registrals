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
                'help' => 'Capacitat màxima simultània. Indica -1 per a béns il·limitats.',
            ])
            ->add('maxYearlyRegistrations', IntegerType::class, [
                'label' => 'Màxim d\'Altes Anuals permeses (Rotació)',
                'attr' => [
                    'class' => 'w-full bg-cream-50/60 border border-steel/30 rounded-xl px-3.5 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-gold focus:bg-white font-mono',
                ],
                'help' => 'Pressupost d\'altes per cicle de 12 mesos (regla anti-carrusel). Free: 20, Plus: 120, Unlimited: -1 o 9999.',
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
            ->add('extraCertificatePriceCents', IntegerType::class, [
                'label' => 'Preu Certificat Oficial Puntual en Cèntims (ex: 590 = 5,90 €)',
                'attr' => [
                    'class' => 'w-full bg-cream-50/60 border border-steel/30 rounded-xl px-3.5 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-gold focus:bg-white font-mono',
                ],
                'help' => 'Preu puntual quan el pla no inclou certificats oficials o se supera la quota.',
            ])
            ->add('kycIncluded', IntegerType::class, [
                'label' => 'Verificacions eIDV (KYC) Incloses',
                'attr' => [
                    'class' => 'w-full bg-cream-50/60 border border-steel/30 rounded-xl px-3.5 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-gold focus:bg-white font-mono',
                ],
                'help' => 'Free: 0, Plus: 1, Unlimited: 9999 o -1.',
            ])
            ->add('kycPriceCents', IntegerType::class, [
                'label' => 'Preu Verificació eIDV (KYC) en Cèntims (ex: 350 = 3,50 €)',
                'attr' => [
                    'class' => 'w-full bg-cream-50/60 border border-steel/30 rounded-xl px-3.5 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-gold focus:bg-white font-mono',
                ],
                'help' => 'Preu per tràmit de validació biomètrica fora de quota inclosa.',
            ])
            ->add('transfersIncluded', IntegerType::class, [
                'label' => 'Traspassos C2C Inclosos al Període',
                'attr' => [
                    'class' => 'w-full bg-cream-50/60 border border-steel/30 rounded-xl px-3.5 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-gold focus:bg-white font-mono',
                ],
                'help' => 'Free: 0, Plus: 5, Unlimited: 9999 o -1.',
            ])
            ->add('extraTransferPriceCents', IntegerType::class, [
                'label' => 'Preu Traspàs Extra en Cèntims d\'Euro (ex: 290 = 2,90 €)',
                'attr' => [
                    'class' => 'w-full bg-cream-50/60 border border-steel/30 rounded-xl px-3.5 py-2 text-xs text-ink focus:outline-none focus:ring-2 focus:ring-gold focus:bg-white font-mono',
                ],
                'help' => '0 per a traspassos gratuïts.',
            ])
            ->add('maxLegacyContacts', IntegerType::class, [
                'label' => 'Persones de Confiança (Herència Digital)',
                'attr' => [
                    'class' => 'w-full bg-cream-50/60 border border-steel/30 rounded-xl px-3.5 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-gold focus:bg-white font-mono',
                ],
                'help' => 'Free: 0, Plus: 1, Unlimited: 5 o -1.',
            ])
            ->add('legacyClaimOpeningFeeCents', IntegerType::class, [
                'label' => 'Taxa Obertura Expedient Llegat en Cèntims (ex: 1900 = 19,00 €)',
                'attr' => [
                    'class' => 'w-full bg-cream-50/60 border border-steel/30 rounded-xl px-3.5 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-gold focus:bg-white font-mono',
                ],
                'help' => 'Taxa Pay-per-Value per cobrir validació documental humana i traspàs per lot.',
            ])
            ->add('allowOfficialCertificates', CheckboxType::class, [
                'label' => 'Permetre Emissió de Certificats Oficials eIDAS / Blockchain',
                'required' => false,
                'attr' => ['class' => 'w-4 h-4 text-ink rounded border-steel/30 focus:ring-gold'],
            ])
            ->add('allowLegacyDesignation', CheckboxType::class, [
                'label' => 'Habilitar Herència Digital & Persona de Confiança',
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
