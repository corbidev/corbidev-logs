<?php

namespace App\Jwt\Repository;

use App\Jwt\Entity\ApiLog;
use App\Jwt\Enum\ApiLogType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class ApiLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiLog::class);
    }

    public function create(
        ApiLogType $type,
        ?string $clientId,
        ?string $ip,
        ?string $path
    ): void {
        $log = new ApiLog($type, $clientId, $ip, $path);

        $em = $this->getEntityManager();
        $em->persist($log);

        // ⚠️ flush volontaire ici (sécurité > perf)
        $em->flush();
    }

    public function deleteOlderThan(\DateTimeImmutable $date): int
    {
        return $this->createQueryBuilder('l')
            ->delete()
            ->andWhere('l.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }
}
