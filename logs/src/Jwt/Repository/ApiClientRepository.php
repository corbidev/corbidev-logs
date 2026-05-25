<?php

namespace App\Jwt\Repository;

use App\Jwt\Entity\ApiClient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class ApiClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiClient::class);
    }

    public function findOneByClientId(string $clientId): ?ApiClient
    {
        return $this->findOneBy(['clientId' => $clientId]);
    }

    public function exists(string $clientId): bool
    {
        return (bool) $this->createQueryBuilder('c')
            ->select('1')
            ->andWhere('c.clientId = :clientId')
            ->setParameter('clientId', $clientId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}