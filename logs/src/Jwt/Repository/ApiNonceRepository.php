<?php

namespace App\Jwt\Repository;

use App\Jwt\Entity\ApiNonce;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class ApiNonceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiNonce::class);
    }

    public function exists(string $nonce): bool
    {
        return (bool) $this->createQueryBuilder('n')
            ->select('1')
            ->andWhere('n.nonce = :nonce')
            ->setParameter('nonce', $nonce)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function deleteExpired(): int
    {
        return $this->createQueryBuilder('n')
            ->delete()
            ->andWhere('n.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }
}