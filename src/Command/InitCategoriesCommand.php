<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:init-categories',
    description: 'Pobla o actualitza les categories i subcategories base B2C/C2C segons la guia SDD',
)]
class InitCategoriesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CategoryRepository $categoryRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Inicialitzant Categories i Subcategories de Registrals');

        $categoriesDefinition = [
            [
                'name' => 'Tecnologia & Electrònica',
                'slug' => 'tecnologia',
                'icon' => 'device-phone-mobile',
                'description' => 'Dispositius electrònics personals, equips informàtics i so d\'alta gamma.',
                'sortOrder' => 10,
                'children' => [
                    [
                        'name' => 'Smartphones & Tauletes',
                        'slug' => 'tecnologia-smartphones-tauletes',
                        'customFields' => [
                            ['name' => 'imei', 'label' => 'Codi IMEI', 'type' => 'text', 'required' => false],
                            ['name' => 'storage_gb', 'label' => 'Capacitat d\'emmagatzematge (GB)', 'type' => 'number', 'required' => false],
                        ],
                    ],
                    [
                        'name' => 'Portàtils & Ordinadors',
                        'slug' => 'tecnologia-portatils-ordinadors',
                        'customFields' => [
                            ['name' => 'processor', 'label' => 'Processador', 'type' => 'text', 'required' => false],
                            ['name' => 'ram_gb', 'label' => 'Memòria RAM (GB)', 'type' => 'number', 'required' => false],
                        ],
                    ],
                    [
                        'name' => 'Fotografia & Vídeo',
                        'slug' => 'tecnologia-fotografia-video',
                        'customFields' => [
                            ['name' => 'lens_mount', 'label' => 'Muntura / Objectiu', 'type' => 'text', 'required' => false],
                        ],
                    ],
                    [
                        'name' => 'Àudio d\'Alta Fidelitat',
                        'slug' => 'tecnologia-audio-hifi',
                    ],
                    [
                        'name' => 'Altres (Tecnologia)',
                        'slug' => 'tecnologia-altres',
                    ],
                ],
            ],
            [
                'name' => 'Joieria, Rellotges & Metalls',
                'slug' => 'joieria-rellotges',
                'icon' => 'sparkles',
                'description' => 'Alta joieria, rellotges de luxe, lingots i metalls preciosos.',
                'sortOrder' => 20,
                'children' => [
                    [
                        'name' => 'Rellotges de Luxe & Col·lecció',
                        'slug' => 'joieria-rellotges-luxe',
                        'customFields' => [
                            ['name' => 'movement', 'label' => 'Tipus de Moviment (Automàtic/Manual/Quars)', 'type' => 'text', 'required' => false],
                            ['name' => 'reference_number', 'label' => 'Número de Referència del Fabricant', 'type' => 'text', 'required' => false],
                        ],
                    ],
                    [
                        'name' => 'Anells, Braçalets & Collarets',
                        'slug' => 'joieria-anells-bracalets-collarets',
                        'customFields' => [
                            ['name' => 'metal_type', 'label' => 'Metall Principal (Or 18k, Platí, Plata 925)', 'type' => 'text', 'required' => false],
                            ['name' => 'gemstone_carats', 'label' => 'Quirats / Gemmes', 'type' => 'text', 'required' => false],
                        ],
                    ],
                    [
                        'name' => 'Monedes & Lingots',
                        'slug' => 'joieria-monedes-lingots',
                        'customFields' => [
                            ['name' => 'weight_grams', 'label' => 'Pes exacte (grams)', 'type' => 'number', 'required' => false],
                            ['name' => 'purity', 'label' => 'Puresa (ex: 999.9)', 'type' => 'text', 'required' => false],
                        ],
                    ],
                    [
                        'name' => 'Altres (Joieria)',
                        'slug' => 'joieria-altres',
                    ],
                ],
            ],
            [
                'name' => 'Mobilitat Urbana & Esport',
                'slug' => 'mobilitat-esport',
                'icon' => 'truck',
                'description' => 'Bicicletes d\'alta gamma, vehicles de mobilitat personal i equipament esportiu.',
                'sortOrder' => 30,
                'children' => [
                    [
                        'name' => 'Bicicletes (Carretera, Gravel, BTT, Urbanes)',
                        'slug' => 'mobilitat-bicicletes',
                        'customFields' => [
                            ['name' => 'frame_number', 'label' => 'Número de Bastidor / Quadre', 'type' => 'text', 'required' => true],
                            ['name' => 'frame_size', 'label' => 'Talla del Quadre (S, M, L o cm)', 'type' => 'text', 'required' => false],
                        ],
                    ],
                    [
                        'name' => 'Patinets & Mobilitat Elèctrica',
                        'slug' => 'mobilitat-patinets-electrics',
                        'customFields' => [
                            ['name' => 'power_watts', 'label' => 'Potència (W)', 'type' => 'number', 'required' => false],
                        ],
                    ],
                    [
                        'name' => 'Material d\'Alta Muntanya & Esquí',
                        'slug' => 'mobilitat-muntanya-esqui',
                    ],
                    [
                        'name' => 'Altres (Mobilitat & Esport)',
                        'slug' => 'mobilitat-altres',
                    ],
                ],
            ],
            [
                'name' => 'Art, Antiguitats & Col·leccions',
                'slug' => 'art-colleccions',
                'icon' => 'photo',
                'description' => 'Obres d\'art, col·leccions, instruments musicals i antiguitats documentades.',
                'sortOrder' => 40,
                'children' => [
                    [
                        'name' => 'Pintura, Gravat & Escultura',
                        'slug' => 'art-pintura-escultura',
                        'customFields' => [
                            ['name' => 'artist', 'label' => 'Artista / Autor', 'type' => 'text', 'required' => false],
                            ['name' => 'medium', 'label' => 'Tècnica / Suport', 'type' => 'text', 'required' => false],
                            ['name' => 'dimensions', 'label' => 'Dimensions (Alt x Ample x Fons en cm)', 'type' => 'text', 'required' => false],
                        ],
                    ],
                    [
                        'name' => 'Col·leccionisme (Segells, Monedes, Còmics, Cartes)',
                        'slug' => 'art-colleccionisme-segells-cartes',
                        'customFields' => [
                            ['name' => 'catalog_ref', 'label' => 'Referència de Catàleg Oficial', 'type' => 'text', 'required' => false],
                            ['name' => 'conservation_grade', 'label' => 'Estat de Conservació (Grading)', 'type' => 'text', 'required' => false],
                        ],
                    ],
                    [
                        'name' => 'Instruments Musicals',
                        'slug' => 'art-instruments-musicals',
                        'customFields' => [
                            ['name' => 'year_built', 'label' => 'Any de construcció', 'type' => 'number', 'required' => false],
                            ['name' => 'luthier', 'label' => 'Luthier / Constructor', 'type' => 'text', 'required' => false],
                        ],
                    ],
                    [
                        'name' => 'Altres (Art & Col·leccions)',
                        'slug' => 'art-altres',
                    ],
                ],
            ],
            [
                'name' => 'Moda & Complements Exclusius',
                'slug' => 'moda-complements',
                'icon' => 'tag',
                'description' => 'Bosses de mà de luxe, marroquineria, sabatilles d\'edició limitada i accessoris.',
                'sortOrder' => 50,
                'children' => [
                    [
                        'name' => 'Bosses de Mà & Marroquineria de Disseny',
                        'slug' => 'moda-bosses-marroquineria',
                        'customFields' => [
                            ['name' => 'authenticity_code', 'label' => 'Codi d\'Autenticitat / Data Code', 'type' => 'text', 'required' => false],
                        ],
                    ],
                    [
                        'name' => 'Sneakers / Sabatilles Edició Limitada',
                        'slug' => 'moda-sneakers-limitades',
                        'customFields' => [
                            ['name' => 'shoe_size', 'label' => 'Talla (EU / US)', 'type' => 'text', 'required' => false],
                        ],
                    ],
                    [
                        'name' => 'Altres (Moda & Complements)',
                        'slug' => 'moda-altres',
                    ],
                ],
            ],
            [
                'name' => 'Altres Béns de Valor',
                'slug' => 'altres-bens',
                'icon' => 'archive-box',
                'description' => 'Qualsevol altre objecte o equipament de valor personal o professional.',
                'sortOrder' => 99,
                'children' => [
                    [
                        'name' => 'Objecte General / No Catalogat',
                        'slug' => 'altres-general',
                    ],
                ],
            ],
        ];

        $rootCount = 0;
        $childCount = 0;

        foreach ($categoriesDefinition as $catDef) {
            $root = $this->categoryRepository->findBySlug($catDef['slug']);
            if (!$root) {
                $root = new Category($catDef['name'], $catDef['slug']);
                $this->entityManager->persist($root);
                $rootCount++;
            } else {
                $root->setName($catDef['name']);
            }

            $root->setDescription($catDef['description'] ?? null);
            $root->setIcon($catDef['icon'] ?? null);
            $root->setSortOrder($catDef['sortOrder'] ?? 0);
            $root->setIsActive(true);

            $childSort = 10;
            foreach ($catDef['children'] ?? [] as $childDef) {
                $child = $this->categoryRepository->findBySlug($childDef['slug']);
                if (!$child) {
                    $child = new Category($childDef['name'], $childDef['slug'], $root);
                    $this->entityManager->persist($child);
                    $childCount++;
                } else {
                    $child->setName($childDef['name']);
                    $child->setParent($root);
                }

                $child->setSortOrder($childSort);
                $child->setIsActive(true);
                $child->setCustomFieldDefinitions($childDef['customFields'] ?? null);

                $childSort += 10;
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf('Categories sincronitzades correctament (%d categories arrel i %d subcategories afegides/actualitzades).', count($categoriesDefinition), $childCount));

        return Command::SUCCESS;
    }
}
