<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * @param array{categoryId?: ?int, storageLocationId?: ?int, expiringWithinDays?: ?int, belowMinStock?: ?bool} $filters
     * @return list<Product>
     */
    public function findForUser(User $user, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.name', 'ASC');

        if (!empty($filters['categoryId'])) {
            $qb->andWhere('IDENTITY(p.category) = :cat')->setParameter('cat', $filters['categoryId']);
        }
        if (!empty($filters['storageLocationId'])) {
            $qb->andWhere('IDENTITY(p.storageLocation) = :loc')->setParameter('loc', $filters['storageLocationId']);
        }
        if (!empty($filters['expiringWithinDays'])) {
            $cutoff = (new \DateTimeImmutable())->modify('+' . (int) $filters['expiringWithinDays'] . ' days');
            $qb->andWhere('p.expirationDate IS NOT NULL AND p.expirationDate <= :cutoff')
                ->setParameter('cutoff', $cutoff);
        }
        if (!empty($filters['belowMinStock'])) {
            $qb->andWhere('p.quantity <= p.minStock');
        }

        /** @var list<Product> $results */
        $results = $qb->getQuery()->getResult();

        return $results;
    }

    /** @return list<Product> */
    public function findExpiringForUser(User $user, int $days): array
    {
        return $this->findForUser($user, ['expiringWithinDays' => $days]);
    }

    /** @return list<Product> */
    public function findShoppingListForUser(User $user): array
    {
        return $this->findForUser($user, ['belowMinStock' => true]);
    }
}
