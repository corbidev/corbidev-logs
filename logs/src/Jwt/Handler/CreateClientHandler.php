<?php

namespace App\Jwt\Handler;

use App\Jwt\Dto\CreateClientInput;
use App\Jwt\Factory\ApiClientFactory;
use Doctrine\ORM\EntityManagerInterface;

final class CreateClientHandler
{
    public function __construct(
        private readonly ApiClientFactory $factory,
        private readonly EntityManagerInterface $em,
    ) {}

    public function handle(CreateClientInput $input): array
    {
        [$client, $plainSecret] = $this->factory->create(
            $input->name,
            $input->description
        );

        $this->em->persist($client);
        $this->em->flush();

        return [
            'client_id' => $client->getClientId(),
            'secret' => $plainSecret, // ⚠️ UNE SEULE FOIS
        ];
    }
}