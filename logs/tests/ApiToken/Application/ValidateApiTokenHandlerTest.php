<?php

declare(strict_types=1);

namespace App\Tests\ApiToken\Application;

use App\ApiToken\Application\ValidateApiTokenHandler;
use App\ApiToken\Application\ValidateApiTokenRequest;
use App\ApiToken\Domain\ApiTokenHasherInterface;
use App\ApiToken\Domain\ApiTokenRepositoryInterface;
use App\ApiToken\Domain\ApiTokenState;
use PHPUnit\Framework\TestCase;

/**
 * Tests nominaux de validation
 * d'un token API.
 */
final class ValidateApiTokenHandlerTest extends TestCase
{
    /**
     * But : Vérifier qu'un token actif est accepté.
     *
     * Entrée : état repository ACTIVE.
     * Résultat attendu : isAccepted()=true, reason='token_active'.
     */
    public function testHandleAcceptsActiveToken(): void
    {
        $hasher = $this->createStub(
            ApiTokenHasherInterface::class,
        );

        $hasher
            ->method('hash')
            ->willReturn(
                str_repeat('e', 64),
            );

        $repository = $this->createStub(
            ApiTokenRepositoryInterface::class,
        );

        $repository
            ->method('resolveStateByHash')
            ->willReturn(ApiTokenState::ACTIVE);

        $handler = new ValidateApiTokenHandler(
            $hasher,
            $repository,
        );

        $result = $handler->handle(
            new ValidateApiTokenRequest('cbi_live_token'),
        );

        self::assertTrue($result->isAccepted());
        self::assertSame('token_active', $result->getReason());
    }

    /**
     * But : Vérifier qu'un token révoqué est refusé.
     *
     * Entrée : état repository REVOKED.
     * Résultat attendu : isRefused()=true, reason='token_revoked'.
     */
    public function testHandleRefusesRevokedToken(): void
    {
        $hasher = $this->createStub(
            ApiTokenHasherInterface::class,
        );

        $hasher
            ->method('hash')
            ->willReturn(
                str_repeat('f', 64),
            );

        $repository = $this->createStub(
            ApiTokenRepositoryInterface::class,
        );

        $repository
            ->method('resolveStateByHash')
            ->willReturn(ApiTokenState::REVOKED);

        $handler = new ValidateApiTokenHandler(
            $hasher,
            $repository,
        );

        $result = $handler->handle(
            new ValidateApiTokenRequest('cbi_revoked_token'),
        );

        self::assertTrue($result->isRefused());
        self::assertSame('token_revoked', $result->getReason());
    }

    /**
     * But : Vérifier qu'un token expiré est refusé.
     *
     * Entrée : état repository EXPIRED.
     * Résultat attendu : isRefused()=true, reason='token_expired'.
     */
    public function testHandleRefusesExpiredToken(): void
    {
        $hasher = $this->createStub(
            ApiTokenHasherInterface::class,
        );

        $hasher
            ->method('hash')
            ->willReturn(
                str_repeat('a', 64),
            );

        $repository = $this->createStub(
            ApiTokenRepositoryInterface::class,
        );

        $repository
            ->method('resolveStateByHash')
            ->willReturn(ApiTokenState::EXPIRED);

        $handler = new ValidateApiTokenHandler(
            $hasher,
            $repository,
        );

        $result = $handler->handle(
            new ValidateApiTokenRequest('cbi_expired_token'),
        );

        self::assertTrue($result->isRefused());
        self::assertSame('token_expired', $result->getReason());
    }
}
