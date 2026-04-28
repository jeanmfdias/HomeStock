<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Table(name: 'categories')]
#[ORM\UniqueConstraint(name: 'categories_slug_unique', columns: ['slug'])]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    private string $name;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    private string $slug;

    #[ORM\Column]
    private bool $requiresExpiration = false;

    public function __construct(string $name, string $slug, bool $requiresExpiration = false)
    {
        $this->name = $name;
        $this->slug = $slug;
        $this->requiresExpiration = $requiresExpiration;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function requiresExpiration(): bool
    {
        return $this->requiresExpiration;
    }

    public function setRequiresExpiration(bool $value): void
    {
        $this->requiresExpiration = $value;
    }
}
