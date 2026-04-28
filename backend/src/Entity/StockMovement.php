<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\StockMovementRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StockMovementRepository::class)]
#[ORM\Table(name: 'stock_movements')]
#[ORM\Index(name: 'stock_movements_product_idx', columns: ['product_id'])]
class StockMovement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'movements')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3)]
    #[Assert\NotEqualTo(value: '0', message: 'delta must be non-zero')]
    private string $delta;

    #[ORM\Column(type: 'string', length: 16, enumType: MovementReason::class)]
    private MovementReason $reason;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $occurredAt;

    public function __construct(Product $product, string $delta, MovementReason $reason)
    {
        $this->product = $product;
        $this->delta = $delta;
        $this->reason = $reason;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getProduct(): Product { return $this->product; }
    public function getDelta(): string { return $this->delta; }
    public function getReason(): MovementReason { return $this->reason; }
    public function getOccurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
