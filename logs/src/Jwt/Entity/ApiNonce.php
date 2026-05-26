<?php

namespace App\Jwt\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Jwt\Repository\ApiNonceRepository;

#[ORM\Entity(repositoryClass: ApiNonceRepository::class)]
#[ORM\Table(name: 'jwt_api_nonce')]
#[ORM\UniqueConstraint(columns: ['nonce'])]
class ApiNonce
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 64)]
    private string $nonce;

    #[ORM\Column(type: 'string', length: 64)]
    private string $clientId;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    public function __construct(string $nonce, string $clientId, \DateTimeImmutable $expiresAt)
    {
        $this->nonce = $nonce;
        $this->clientId = $clientId;
        $this->expiresAt = $expiresAt;
    }

    public function getNonce(): string { return $this->nonce; }

    public function getClientId(): string { return $this->clientId; }

    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }
}