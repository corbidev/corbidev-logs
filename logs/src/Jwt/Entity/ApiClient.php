<?php

namespace App\Jwt\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Jwt\Repository\ApiClientRepository;

#[ORM\Entity(repositoryClass: ApiClientRepository::class)]
#[ORM\Table(name: 'jwt_api_client')]
class ApiClient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private string $clientId;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: ApiClientSecret::class, cascade: ['persist'], orphanRemoval: true)]
    private iterable $secrets;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->secrets = new \Doctrine\Common\Collections\ArrayCollection();
    }

    // --- getters/setters ---

    public function getId(): ?int { return $this->id; }

    public function getClientId(): string { return $this->clientId; }
    public function setClientId(string $clientId): self { $this->clientId = $clientId; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getRevokedAt(): ?\DateTimeImmutable { return $this->revokedAt; }
    public function revoke(): void { $this->revokedAt = new \DateTimeImmutable(); }

    public function getSecrets(): iterable { return $this->secrets; }

    public function addSecret(ApiClientSecret $secret): void
    {
        $this->secrets[] = $secret;
    }
}
