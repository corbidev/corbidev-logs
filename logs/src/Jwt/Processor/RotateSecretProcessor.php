<?php

namespace App\Jwt\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Jwt\Dto\RotateSecretInput;
use App\Jwt\Entity\ApiClient;
use App\Jwt\Handler\RotateSecretHandler;
use App\Jwt\Repository\ApiClientRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RotateSecretProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly RotateSecretHandler $handler,
        private readonly ApiClientRepository $clientRepository
    ) {}

    /**
     * @param RotateSecretInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof RotateSecretInput) {
            throw new \InvalidArgumentException('Invalid DTO for RotateSecretProcessor');
        }

        $clientId = $uriVariables['id'] ?? null;

        if (!$clientId) {
            throw new \InvalidArgumentException('Missing client id');
        }

        /** @var ApiClient|null $client */
        $client = $this->clientRepository->findOneBy(['clientId' => $clientId]);

        if (!$client) {
            throw new NotFoundHttpException('Client not found');
        }

        return $this->handler->handle($client, $data);
    }
}