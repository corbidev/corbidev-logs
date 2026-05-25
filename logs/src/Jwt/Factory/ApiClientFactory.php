<?php

namespace App\Jwt\Factory;

use App\Jwt\Entity\ApiClient;
use App\Jwt\Entity\ApiClientSecret;
use App\Jwt\Security\SecretCrypto;

final class ApiClientFactory
{
    public function __construct(
        private readonly SecretCrypto $crypto
    ) {}

    /**
     * Crée un ApiClient + son premier secret
     * Retourne [ApiClient, plainSecret]
     */
    public function create(string $name, ?string $description = null): array
    {
        $clientId = $this->generateClientId();
        $plainSecret = $this->generateSecret();

        $client = new ApiClient();
        $client->setClientId($clientId);
        $client->setName($name);
        $client->setDescription($description);

        $encryptedSecret = $this->crypto->encrypt($plainSecret);

        $secret = new ApiClientSecret($client, $encryptedSecret);
        $client->addSecret($secret);

        return [$client, $plainSecret];
    }

    /**
     * Génère un nouveau secret pour rotation
     * Retourne [ApiClientSecret, plainSecret]
     */
    public function createSecret(ApiClient $client): array
    {
        $plainSecret = $this->generateSecret();
        $encryptedSecret = $this->crypto->encrypt($plainSecret);

        $secret = new ApiClientSecret($client, $encryptedSecret);

        return [$secret, $plainSecret];
    }

    private function generateClientId(): string
    {
        return 'cli_' . bin2hex(random_bytes(16));
    }

    private function generateSecret(): string
    {
        return bin2hex(random_bytes(32));
    }
}