<?php

namespace App\Jwt\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Jwt\Repository\ApiClientSecretRepository;

#[ORM\Entity(repositoryClass: ApiClientSecretRepository::class)]
#[ORM\Table(name: 'jwt_api_client_secret')]
class ApiClientSecret
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ApiClient::class, inversedBy: 'secrets')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ApiClient $client;

    #[ORM\Column(type: 'text')]
    private string $secretEncrypted;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    public function __construct(ApiClient $client, string $secretEncrypted)
    {
        $this->client = $client;
        $this->secretEncrypted = $secretEncrypted;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getClient(): ApiClient { return $this->client; }

    public function getSecretEncrypted(): string { return $this->secretEncrypted; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getRevokedAt(): ?\DateTimeImmutable { return $this->revokedAt; }

    public function revoke(): void
    {
        $this->revokedAt = new \DateTimeImmutable();
    }

    public function isActive(): bool
    {
        return $this->revokedAt === null;
    }
}
