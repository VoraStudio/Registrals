<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Category;
use App\Entity\Item;
use App\Enum\AcquisitionType;
use App\Repository\CategoryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ItemType extends AbstractType
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom o Títol del Bé',
                'attr' => [
                    'placeholder' => 'Ex: Bicicleta Gravel Canyon Grizl 7, Rellotge Omega Seamaster...',
                    'class' => 'w-full px-4 py-3 rounded-xl bg-white/90 border border-steel/30 text-ink focus:border-gold focus:ring-2 focus:ring-gold/30',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Si us plau, indica un nom per identificar el teu bé.']),
                    new Length(['max' => 200]),
                ],
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choices' => $this->categoryRepository->findRootCategories(),
                'choice_label' => 'name',
                'label' => 'Categoria Principal',
                'placeholder' => 'Selecciona una categoria...',
                'attr' => [
                    'class' => 'w-full px-4 py-3 rounded-xl bg-white/90 border border-steel/30 text-ink text-sm font-raleway focus:border-gold focus:ring-2 focus:ring-gold/30',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Selecciona una categoria principal.']),
                ],
            ])
            ->add('subcategory', EntityType::class, [
                'class' => Category::class,
                'choices' => $this->categoryRepository->findSubcategories(),
                'choice_label' => 'name',
                'label' => 'Subcategoria Específica',
                'required' => false,
                'placeholder' => 'Selecciona primer una categoria...',
                'attr' => [
                    'class' => 'w-full px-4 py-3 rounded-xl bg-white/90 border border-steel/30 text-ink text-sm font-raleway placeholder-steel/60 focus:border-gold focus:ring-2 focus:ring-gold/30',
                ],
            ])
            ->add('brand', TextType::class, [
                'label' => 'Marca / Fabricant',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: Apple, Canyon, Rolex, Specialized...',
                    'class' => 'w-full px-4 py-3 rounded-xl bg-white/90 border border-steel/30 text-ink text-sm font-raleway focus:border-gold focus:ring-2 focus:ring-gold/30',
                ],
            ])
            ->add('model', TextType::class, [
                'label' => 'Model / Versió',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: iPhone 15 Pro, Grizl CF SL 7, Submariner Date...',
                    'class' => 'w-full px-4 py-3 rounded-xl bg-white/90 border border-steel/30 text-ink text-sm font-raleway focus:border-gold focus:ring-2 focus:ring-gold/30',
                ],
            ])
            ->add('serialNumber', TextType::class, [
                'label' => 'Número de Sèrie / Bastidor / IMEI',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: SN12345678, Bastidor o Codi d\'autenticitat...',
                    'class' => 'w-full px-4 py-3 rounded-xl bg-white/90 border border-steel/30 text-ink text-sm font-raleway focus:border-gold focus:ring-2 focus:ring-gold/30',
                ],
                'help' => 'Element clau per a la identificació policial i asseguradora en cas de pèrdua o robatori.',
            ])
            ->add('declaredValueEuros', \Symfony\Component\Form\Extension\Core\Type\NumberType::class, [
                'label' => 'Valor Estimat / Compra (€)',
                'required' => false,
                'scale' => 2,
                'html5' => false,
                'attr' => [
                    'placeholder' => '0,00',
                    'class' => 'w-full px-4 py-3 rounded-xl bg-white/90 border border-steel/30 text-ink text-sm font-raleway placeholder-steel/60 focus:border-gold focus:ring-2 focus:ring-gold/30',
                ],
            ])
            ->add('acquisitionType', ChoiceType::class, [
                'label' => 'Origen / Transmissió de Propietat',
                'choices' => [
                    AcquisitionType::PURCHASE->label() => AcquisitionType::PURCHASE,
                    AcquisitionType::INHERITANCE->label() => AcquisitionType::INHERITANCE,
                    AcquisitionType::GIFT->label() => AcquisitionType::GIFT,
                    AcquisitionType::PRODUCTION->label() => AcquisitionType::PRODUCTION,
                    AcquisitionType::OTHER->label() => AcquisitionType::OTHER,
                ],
                'attr' => [
                    'class' => 'w-full px-4 py-3 rounded-xl bg-white/90 border border-steel/30 text-ink focus:border-gold focus:ring-2 focus:ring-gold/30',
                ],
            ])
            ->add('acquisitionDate', DateType::class, [
                'label' => 'Data d\'Adquisició (Aproximada)',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
                'attr' => [
                    'class' => 'w-full px-4 py-3 rounded-xl bg-white/90 border border-steel/30 text-ink focus:border-gold focus:ring-2 focus:ring-gold/30',
                ],
            ])
            ->add('acquisitionCircumstances', TextareaType::class, [
                'label' => 'Circumstàncies d\'Adquisició / Observacions',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Detalls addicionals sobre la compra, establiment, context o característiques particulars del bé...',
                    'class' => 'w-full px-4 py-3 rounded-xl bg-white/90 border border-steel/30 text-ink focus:border-gold focus:ring-2 focus:ring-gold/30',
                ],
            ])
            // Camps per a la càrrega de fitxers i documents (Step 2)
            ->add('primaryPhoto', FileType::class, [
                'label' => 'Fotografia Principal del Bé',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '15M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'image/heic',
                            'image/heif',
                        ],
                        'mimeTypesMessage' => 'Si us plau, puja una imatge vàlida (JPG, PNG, WebP o HEIC).',
                    ]),
                ],
                'attr' => [
                    'accept' => 'image/*',
                    'class' => 'sr-only',
                ],
            ])
            ->add('invoiceFile', FileType::class, [
                'label' => 'Factura / Tiquet / Comprovant de Pagament',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '20M',
                        'mimeTypes' => [
                            'application/pdf',
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Puja un PDF o una fotografia del comprovant (JPG, PNG, WebP).',
                    ]),
                ],
                'attr' => [
                    'accept' => 'image/*,application/pdf',
                    'class' => 'sr-only',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Item::class,
        ]);
    }
}
