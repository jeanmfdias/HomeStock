<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Batch;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Batch>
 */
class BatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Batch::class);
    }

    public function findOneByProductAndExpiration(Product $product, ?\DateTimeImmutable $expirationDate): ?Batch
    {
        $qb = $this->createQueryBuilder('b')
            ->andWhere('b.product = :product')
            ->setParameter('product', $product);

        if (null === $expirationDate) {
            $qb->andWhere('b.expirationDate IS NULL');
        } else {
            $qb->andWhere('b.expirationDate = :exp')
                ->setParameter('exp', $expirationDate->format('Y-m-d'));
        }

        /** @var Batch|null $result */
        $result = $qb->setMaxResults(1)->getQuery()->getOneOrNullResult();

        return $result;
    }

    /**
     * @return list<Batch>
     */
    public function findExpiringBatchesForUser(User $user, int $days): array
    {
        $cutoff = (new \DateTimeImmutable())->modify('+'.$days.' days');

        $qb = $this->createQueryBuilder('b')
            ->innerJoin('b.product', 'p')
            ->addSelect('p')
            ->andWhere('p.user = :user')
            ->andWhere('b.expirationDate IS NOT NULL')
            ->andWhere('b.expirationDate <= :cutoff')
            ->setParameter('user', $user)
            ->setParameter('cutoff', $cutoff)
            ->orderBy('b.expirationDate', 'ASC');

        /** @var list<Batch> $results */
        $results = $qb->getQuery()->getResult();

        return $results;
    }
}
