<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Table(name: 'categories')]
#[ORM\Index(columns: ['slug'], name: 'idx_categories_slug')]
#[ORM\Index(columns: ['parent_id', 'is_active'], name: 'idx_categories_parent_active')]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name;

    #[ORM\Column(type: 'string', length: 120, unique: true)]
    private string $slug;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 60, nullable: true)]
    private ?string $icon = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Category $parent = null;

    /**
     * @var Collection<int, Category>
     */
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'name' => 'ASC'])]
    private Collection $children;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $customFieldDefinitions = null;

    /**
     * @var Collection<int, Item>
     */
    #[ORM\OneToMany(mappedBy: 'category', targetEntity: Item::class)]
    private Collection $items;

    public function __construct(string $name, string $slug, ?Category $parent = null)
    {
        $this->name = $name;
        $this->slug = $slug;
        $this->parent = $parent;
        $this->children = new ArrayCollection();
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }

    public function getParent(): ?Category
    {
        return $this->parent;
    }

    public function setParent(?Category $parent): self
    {
        $this->parent = $parent;
        return $this;
    }

    public function isRoot(): bool
    {
        return $this->parent === null;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(Category $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }
        return $this;
    }

    public function removeChild(Category $child): self
    {
        if ($this->children->removeElement($child)) {
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }
        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCustomFieldDefinitions(): ?array
    {
        return $this->customFieldDefinitions;
    }

    /**
     * @param array<string, mixed>|null $customFieldDefinitions
     */
    public function setCustomFieldDefinitions(?array $customFieldDefinitions): self
    {
        $this->customFieldDefinitions = $customFieldDefinitions;
        return $this;
    }

    /**
     * @return Collection<int, Item>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function __toString(): string
    {
        if ($this->parent !== null) {
            return $this->parent->getName() . ' — ' . $this->name;
        }
        return $this->name;
    }
}
