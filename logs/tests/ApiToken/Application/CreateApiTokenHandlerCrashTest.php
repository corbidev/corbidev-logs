<?php

declare(strict_types=1);

namespace App\Tests\ApiToken\Application;

use App\ApiToken\Application\CreateApiTokenHandler;
use App\ApiToken\Application\CreateApiTokenRequest;
use App\ApiToken\Domain\ApiTokenGeneratorInterface;
use App\ApiToken\Domain\ApiTokenHasherInterface;
use App\ApiToken\Domain\ApiTokenRepositoryInterface;
use PHPUnit\Framework\TestCase;

/**
 * Crash tests du handler
 * de création de token API.
 */
final class CreateApiTokenHandlerCrashTest extends TestCase
{
    /**
     * But : Vérifier que le handler encapsule une panne de persistence avec un message explicite.
     *
     * Entrée : repository->store() lève RuntimeException('duplicate token hash').
     * Résultat attendu : RuntimeException préfixée 'Api token creation failed:'.
     */
    public function testHandleWrapsTechnicalRepositoryFailure(): void
    {
        $generator = $this->createStub(
            ApiTokenGeneratorInterface::class,
        );

        $generator
            ->method('generate')
            ->willReturn(
                'cbi_1234567890abcdef1234567890abcdef1234567890abcdef1234567890',
            );

        $hasher = $this->createStub(
            ApiTokenHasherInterface::class,
        );

        $hasher
            ->method('hash')
            ->willReturn(
                str_repeat('b', 64),
            );

        $repository = $this->createMock(
            ApiTokenRepositoryInterface::class,
        );

        $repository
            ->expects(self::once())
            ->method('store')
            ->willThrowException(
                new \RuntimeException(
                    'duplicate token hash',
                ),
            );

        $handler = new CreateApiTokenHandler(
            $generator,
            $hasher,
            $repository,
        );

        $this->expectException(
            \RuntimeException::class,
        );

        $this->expectExceptionMessage(
            'Api token creation failed: duplicate token hash',
        );

        $handler->handle(
            new CreateApiTokenRequest(
                domainId: 1,
                label: 'ci token',
            ),
        );
    }
}
