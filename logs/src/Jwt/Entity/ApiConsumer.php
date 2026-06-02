<?php

namespace App\Jwt\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ApiConsumer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(unique: true)]
    private string $identifier;

    #[ORM\Column(name: 'domain_id')]
    private int $domainId;

    #[ORM\Column]
    private string $passwordHash;

    #[ORM\Column]
    private bool $active = true;

    public function __construct(string $identifier, string $passwordHash, int $domainId)
    {
        $this->identifier = $identifier;
        $this->passwordHash = $passwordHash;
        $this->domainId = $domainId;
        $this->active = true;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getDomainId(): int
    {
        return $this->domainId;
    }

    public function setDomainId(int $domainId): void
    {
        $this->domainId = $domainId;
    }

    public function setPasswordHash(string $password): void
    {
        $this->passwordHash = $password;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }
}
