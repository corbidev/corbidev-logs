<?php

namespace App\Jwt\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Jwt\Dto\CreateClientInput;
use App\Jwt\Handler\CreateClientHandler;

final class CreateClientProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CreateClientHandler $handler
    ) {}

    /**
     * @param CreateClientInput $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof CreateClientInput) {
            throw new \InvalidArgumentException('Invalid DTO for CreateClientProcessor');
        }

        return $this->handler->handle($data);
    }
}