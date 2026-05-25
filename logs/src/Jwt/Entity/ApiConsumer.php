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

    #[ORM\Column]
    private string $passwordHash;

    #[ORM\Column]
    private bool $active = true;

    public function __construct(string $identifier, string $passwordHash)
    {
        $this->identifier = $identifier;
        $this->passwordHash = $passwordHash;
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