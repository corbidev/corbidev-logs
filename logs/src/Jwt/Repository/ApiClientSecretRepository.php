<?php

namespace App\Jwt\Repository;

use App\Jwt\Entity\ApiClientSecret;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class ApiClientSecretRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiClientSecret::class);
    }

    /**
     * Supprime les secrets révoqués anciens (cleanup)
     */
    public function deleteRevokedOlderThan(\DateTimeImmutable $date): int
    {
        return $this->createQueryBuilder('s')
            ->delete()
            ->andWhere('s.revokedAt IS NOT NULL')
            ->andWhere('s.revokedAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }
}