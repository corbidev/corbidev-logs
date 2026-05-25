<?php

declare(strict_types=1);

namespace App\Tests\ApiToken\Infrastructure;

use App\ApiToken\Domain\ApiTokenState;
use App\ApiToken\Domain\ApiTokenToStore;
use App\ApiToken\Infrastructure\DoctrineApiTokenRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Tests du repository Doctrine
 * des tokens API hashés.
 */
final class DoctrineApiTokenRepositoryTest extends TestCase
{
    /**
     * But : Vérifier que store() écrit le hash en table api_tokens.
     *
     * Entrée : ApiTokenToStore valide.
     * Résultat attendu : insert() appelé avec token_hash et sans token clair.
     */
    public function testStorePersistsHashedTokenPayload(): void
    {
        $connection = $this->createMock(
            Connection::class,
        );

        $connection
            ->expects(self::once())
            ->method('insert')
            ->with(
                'api_tokens',
                self::callback(
                    static function (array $data): bool {
                        return ($data['project_id'] ?? null) === 1
                            && ($data['token_hash'] ?? null) === str_repeat('c', 64)
                            && ($data['token_prefix'] ?? null) === 'cbi_prefix_01'
                            && ($data['label'] ?? null) === 'integration token'
                            && array_key_exists('expires_at', $data)
                            && !array_key_exists('plain_token', $data);
                    },
                ),
            )
            ->willReturn(1);

        $repository = new DoctrineApiTokenRepository(
            $connection,
        );

        $repository->store(
            new ApiTokenToStore(
                projectId: 1,
                tokenHash: str_repeat('c', 64),
                tokenPrefix: 'cbi_prefix_01',
                label: 'integration token',
                expiresAt: new \DateTimeImmutable(
                    '2030-01-01 00:00:00',
                ),
            ),
        );

        self::assertTrue(true);
    }

    /**
     * But : Vérifier que store() remonte une erreur technique explicite en cas d'échec SQL.
     *
     * Entrée : insert() lève RuntimeException('connection lost').
     * Résultat attendu : RuntimeException préfixée 'Api token hash persistence failed:'.
     */
    public function testStoreThrowsExplicitTechnicalErrorWhenSqlFails(): void
    {
        $connection = $this->createMock(
            Connection::class,
        );

        $connection
            ->expects(self::once())
            ->method('insert')
            ->willThrowException(
                new \RuntimeException(
                    'connection lost',
                ),
            );

        $repository = new DoctrineApiTokenRepository(
            $connection,
        );

        $this->expectException(
            \RuntimeException::class,
        );

        $this->expectExceptionMessage(
            'Api token hash persistence failed: connection lost',
        );

        $repository->store(
            new ApiTokenToStore(
                projectId: 1,
                tokenHash: str_repeat('d', 64),
                tokenPrefix: 'cbi_prefix_02',
                label: 'runtime token',
                expiresAt: null,
            ),
        );
    }

    /**
     * But : Vérifier que resolveStateByHash() retourne ACTIVE pour un token non révoqué et non expiré.
     *
     * Entrée : fetchAssociative() avec revoked_at=null et expires_at futur.
     * Résultat attendu : ApiTokenState::ACTIVE.
     */
    public function testResolveStateByHashReturnsActiveWhenTokenIsUsable(): void
    {
        $connection = $this->createMock(
            Connection::class,
        );

        $connection
            ->expects(self::once())
            ->method('fetchAssociative')
            ->willReturn(
                [
                    'revoked_at' => null,
                    'expires_at' => '2030-01-01 00:00:00',
                ],
            );

        $repository = new DoctrineApiTokenRepository(
            $connection,
        );

        $state = $repository->resolveStateByHash(
            str_repeat('1', 64),
            new \DateTimeImmutable('2029-01-01 00:00:00'),
        );

        self::assertSame(
            ApiTokenState::ACTIVE,
            $state,
        );
    }

    /**
     * But : Vérifier que resolveStateByHash() refuse un token révoqué.
     *
     * Entrée : fetchAssociative() avec revoked_at non null.
     * Résultat attendu : ApiTokenState::REVOKED.
     */
    public function testResolveStateByHashReturnsRevokedWhenRevocationDateExists(): void
    {
        $connection = $this->createMock(
            Connection::class,
        );

        $connection
            ->expects(self::once())
            ->method('fetchAssociative')
            ->willReturn(
                [
                    'revoked_at' => '2026-01-01 00:00:00',
                    'expires_at' => '2030-01-01 00:00:00',
                ],
            );

        $repository = new DoctrineApiTokenRepository(
            $connection,
        );

        $state = $repository->resolveStateByHash(
            str_repeat('2', 64),
            new \DateTimeImmutable('2026-02-01 00:00:00'),
        );

        self::assertSame(
            ApiTokenState::REVOKED,
            $state,
        );
    }

    /**
     * But : Vérifier que resolveStateByHash() refuse un token expiré.
     *
     * Entrée : fetchAssociative() avec expires_at passé.
     * Résultat attendu : ApiTokenState::EXPIRED.
     */
    public function testResolveStateByHashReturnsExpiredWhenExpirationDateIsPast(): void
    {
        $connection = $this->createMock(
            Connection::class,
        );

        $connection
            ->expects(self::once())
            ->method('fetchAssociative')
            ->willReturn(
                [
                    'revoked_at' => null,
                    'expires_at' => '2025-01-01 00:00:00',
                ],
            );

        $repository = new DoctrineApiTokenRepository(
            $connection,
        );

        $state = $repository->resolveStateByHash(
            str_repeat('3', 64),
            new \DateTimeImmutable('2026-01-01 00:00:00'),
        );

        self::assertSame(
            ApiTokenState::EXPIRED,
            $state,
        );
    }

    /**
     * But : Vérifier que revokeByHash() retourne true si une ligne est révoquée.
     *
     * Entrée : executeStatement() retourne 1.
     * Résultat attendu : true.
     */
    public function testRevokeByHashReturnsTrueWhenUpdateAffectsRow(): void
    {
        $connection = $this->createMock(
            Connection::class,
        );

        $connection
            ->expects(self::once())
            ->method('executeStatement')
            ->willReturn(1);

        $repository = new DoctrineApiTokenRepository(
            $connection,
        );

        $result = $repository->revokeByHash(
            str_repeat('4', 64),
            new \DateTimeImmutable('2026-05-25 12:00:00'),
        );

        self::assertTrue($result);
    }
}
