<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'products')]
#[ORM\Index(name: 'products_user_idx', columns: ['user_id'])]
#[ORM\Index(name: 'products_expiration_idx', columns: ['expiration_date'])]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    private string $name;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $brand = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Category $category;

    #[ORM\ManyToOne(targetEntity: StorageLocation::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?StorageLocation $storageLocation = null;

    #[ORM\ManyToOne(targetEntity: Store::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Store $preferredStore = null;

    #[ORM\Column(type: 'string', length: 8, enumType: UnitType::class)]
    private UnitType $unitType = UnitType::UNIT;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3)]
    #[Assert\PositiveOrZero]
    private string $quantity = '0';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3)]
    #[Assert\PositiveOrZero]
    private string $minStock = '0';

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $expirationDate = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, StockMovement> */
    #[ORM\OneToMany(targetEntity: StockMovement::class, mappedBy: 'product', cascade: ['remove'], orphanRemoval: true)]
    private Collection $movements;

    public function __construct(User $user, string $name, Category $category)
    {
        $this->user = $user;
        $this->name = $name;
        $this->category = $category;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->movements = new ArrayCollection();
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getName(): string { return $this->name; }
    public function setName(string $v): void { $this->name = $v; }
    public function getBrand(): ?string { return $this->brand; }
    public function setBrand(?string $v): void { $this->brand = $v; }
    public function getCategory(): Category { return $this->category; }
    public function setCategory(Category $v): void { $this->category = $v; }
    public function getStorageLocation(): ?StorageLocation { return $this->storageLocation; }
    public function setStorageLocation(?StorageLocation $v): void { $this->storageLocation = $v; }
    public function getPreferredStore(): ?Store { return $this->preferredStore; }
    public function setPreferredStore(?Store $v): void { $this->preferredStore = $v; }
    public function getUnitType(): UnitType { return $this->unitType; }
    public function setUnitType(UnitType $v): void { $this->unitType = $v; }
    public function getQuantity(): string { return $this->quantity; }
    public function setQuantity(string $v): void { $this->quantity = $v; }
    public function getMinStock(): string { return $this->minStock; }
    public function setMinStock(string $v): void { $this->minStock = $v; }
    public function getExpirationDate(): ?\DateTimeImmutable { return $this->expirationDate; }
    public function setExpirationDate(?\DateTimeImmutable $v): void { $this->expirationDate = $v; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): void { $this->notes = $v; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    /** @return Collection<int, StockMovement> */
    public function getMovements(): Collection { return $this->movements; }

    public function isBelowMinStock(): bool
    {
        return bccomp($this->quantity, $this->minStock, 3) <= 0;
    }
}
