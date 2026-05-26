<?php

namespace App\Jwt\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Jwt\Repository\ApiLogRepository;
use App\Jwt\Enum\ApiLogType;

#[ORM\Entity(repositoryClass: ApiLogRepository::class)]
#[ORM\Table(name: 'jwt_api_log')]
class ApiLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $clientId = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $type;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $path = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        ApiLogType $type,
        ?string $clientId = null,
        ?string $ip = null,
        ?string $path = null
    ) {
        $this->type = $type->value;
        $this->clientId = $clientId;
        $this->ip = $ip;
        $this->path = $path;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
}
