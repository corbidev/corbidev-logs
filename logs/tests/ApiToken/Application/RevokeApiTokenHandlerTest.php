<?php

declare(strict_types=1);

namespace App\Tests\ApiToken\Application;

use App\ApiToken\Application\RevokeApiTokenHandler;
use App\ApiToken\Application\RevokeApiTokenRequest;
use App\ApiToken\Domain\ApiTokenHasherInterface;
use App\ApiToken\Domain\ApiTokenRepositoryInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests du handler de révocation
 * de token API.
 */
final class RevokeApiTokenHandlerTest extends TestCase
{
    /**
     * But : Vérifier que handle() révoque un token via son hash.
     *
     * Entrée : token clair valide.
     * Résultat attendu : repository->revokeByHash() appelé, résultat true.
     */
    public function testHandleRevokesTokenByHash(): void
    {
        $hasher = $this->createMock(
            ApiTokenHasherInterface::class,
        );

        $repository = $this->createMock(
            ApiTokenRepositoryInterface::class,
        );

        $hasher
            ->expects(self::once())
            ->method('hash')
            ->with('cbi_to_revoke')
            ->willReturn(str_repeat('9', 64));

        $repository
            ->expects(self::once())
            ->method('revokeByHash')
            ->with(
                str_repeat('9', 64),
                self::isInstanceOf(\DateTimeImmutable::class),
            )
            ->willReturn(true);

        $handler = new RevokeApiTokenHandler(
            $hasher,
            $repository,
        );

        $result = $handler->handle(
            new RevokeApiTokenRequest('cbi_to_revoke'),
        );

        self::assertTrue($result);
    }

    /**
     * But : Vérifier que handle() remonte une erreur technique explicite en cas de panne repository.
     *
     * Entrée : revokeByHash() lève RuntimeException.
     * Résultat attendu : RuntimeException préfixée 'Api token revocation failed:'.
     */
    public function testHandleWrapsTechnicalRevocationFailure(): void
    {
        $hasher = $this->createStub(
            ApiTokenHasherInterface::class,
        );

        $hasher
            ->method('hash')
            ->willReturn(str_repeat('8', 64));

        $repository = $this->createMock(
            ApiTokenRepositoryInterface::class,
        );

        $repository
            ->expects(self::once())
            ->method('revokeByHash')
            ->willThrowException(
                new \RuntimeException('db unavailable'),
            );

        $handler = new RevokeApiTokenHandler(
            $hasher,
            $repository,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Api token revocation failed: db unavailable');

        $handler->handle(
            new RevokeApiTokenRequest('cbi_fail_revoke'),
        );
    }
}
