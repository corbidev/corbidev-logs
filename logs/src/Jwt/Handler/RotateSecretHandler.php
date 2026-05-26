<?php

namespace App\Jwt\Handler;

use App\Jwt\Dto\RotateSecretInput;
use App\Jwt\Entity\ApiClient;
use App\Jwt\Factory\ApiClientFactory;
use Doctrine\ORM\EntityManagerInterface;

final class RotateSecretHandler
{
    public function __construct(
        private readonly ApiClientFactory $factory,
        private readonly EntityManagerInterface $em,
    ) {}

    public function handle(ApiClient $client, RotateSecretInput $input): array
    {
        // 🔁 création nouveau secret
        [$secret, $plainSecret] = $this->factory->createSecret($client);
        $client->addSecret($secret);

        if ($input->revokePrevious === true) {
            foreach ($client->getSecrets() as $existing) {
                if ($existing !== $secret && $existing->isActive()) {
                    $existing->revoke();
                }
            }
        } else {
            // rotation douce : garder max 2 actifs
            $activeSecrets = array_filter(
                iterator_to_array($client->getSecrets()),
                fn($s) => $s->isActive()
            );

            if (count($activeSecrets) > 2) {
                // on révoque les plus anciens
                usort($activeSecrets, fn($a, $b) => $a->getCreatedAt() <=> $b->getCreatedAt());

                while (count($activeSecrets) > 2) {
                    $old = array_shift($activeSecrets);
                    $old->revoke();
                }
            }
        }

        $this->em->flush();

        return [
            'client_id' => $client->getClientId(),
            'secret' => $plainSecret, // ⚠️ UNE SEULE FOIS
        ];
    }
}
