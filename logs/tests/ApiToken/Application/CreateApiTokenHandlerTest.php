<?php

declare(strict_types=1);

namespace App\Tests\ApiToken\Application;

use App\ApiToken\Application\CreateApiTokenHandler;
use App\ApiToken\Application\CreateApiTokenRequest;
use App\ApiToken\Domain\ApiTokenGeneratorInterface;
use App\ApiToken\Domain\ApiTokenHasherInterface;
use App\ApiToken\Domain\ApiTokenRepositoryInterface;
use App\ApiToken\Domain\ApiTokenToStore;
use PHPUnit\Framework\TestCase;

/**
 * Tests nominaux du handler
 * de création de token API.
 */
final class CreateApiTokenHandlerTest extends TestCase
{
    /**
     * But : Vérifier que le handler génère un token opaque et persiste uniquement son hash.
     *
     * Entrée : Request valide (projectId=1, label='CI token').
     * Résultat attendu : token clair retourné, hash persisté, préfixe stable sur 12 caractères.
     */
    public function testHandleGeneratesOpaqueTokenAndStoresHashOnly(): void
    {
        $generator = $this->createMock(
            ApiTokenGeneratorInterface::class,
        );

        $hasher = $this->createMock(
            ApiTokenHasherInterface::class,
        );

        $repository = $this->createMock(
            ApiTokenRepositoryInterface::class,
        );

        $plainToken = 'cbi_abcdef1234567890abcdef1234567890abcdef1234567890abcdef123456';
        $hash = str_repeat('a', 64);

        $generator
            ->expects(self::once())
            ->method('generate')
            ->willReturn($plainToken);

        $hasher
            ->expects(self::once())
            ->method('hash')
            ->with($plainToken)
            ->willReturn($hash);

        $repository
            ->expects(self::once())
            ->method('store')
            ->with(
                self::callback(
                    static function (ApiTokenToStore $tokenToStore) use ($hash): bool {
                        return $tokenToStore->getProjectId() === 1
                            && $tokenToStore->getLabel() === 'CI token'
                            && $tokenToStore->getTokenHash() === $hash
                            && $tokenToStore->getTokenPrefix() === 'cbi_abcdef12';
                    },
                ),
            );

        $handler = new CreateApiTokenHandler(
            $generator,
            $hasher,
            $repository,
        );

        $result = $handler->handle(
            new CreateApiTokenRequest(
                projectId: 1,
                label: 'CI token',
            ),
        );

        self::assertSame(
            $plainToken,
            $result->getPlainToken(),
        );

        self::assertSame(
            'cbi_abcdef12',
            $result->getTokenPrefix(),
        );
    }
}
