<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BatchRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: BatchRepository::class)]
#[ORM\Table(name: 'batches')]
#[ORM\Index(name: 'batches_product_idx', columns: ['product_id'])]
#[ORM\Index(name: 'batches_expiration_idx', columns: ['expiration_date'])]
#[ORM\Index(name: 'batches_product_expiration_idx', columns: ['product_id', 'expiration_date'])]
class Batch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'batches')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3)]
    #[Assert\PositiveOrZero]
    private string $quantity = '0';

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $expirationDate = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, StockMovement> */
    #[ORM\OneToMany(targetEntity: StockMovement::class, mappedBy: 'batch', cascade: ['remove'], orphanRemoval: true)]
    private Collection $movements;

    public function __construct(Product $product, string $quantity = '0', ?\DateTimeImmutable $expirationDate = null)
    {
        $this->product = $product;
        $this->quantity = $quantity;
        $this->expirationDate = $expirationDate;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->movements = new ArrayCollection();
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function setQuantity(string $v): void
    {
        $this->quantity = $v;
    }

    public function getExpirationDate(): ?\DateTimeImmutable
    {
        return $this->expirationDate;
    }

    public function setExpirationDate(?\DateTimeImmutable $v): void
    {
        $this->expirationDate = $v;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, StockMovement> */
    public function getMovements(): Collection
    {
        return $this->movements;
    }

    #[Assert\Callback]
    public function validateExpiration(ExecutionContextInterface $context): void
    {
        if ($this->product->getCategory()->requiresExpiration() && null === $this->expirationDate) {
            $context->buildViolation('expiration_required_for_category')
                ->atPath('expirationDate')
                ->addViolation();
        }
    }
}
