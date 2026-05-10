<?php

declare(strict_types=1);

namespace App\Log\Application\Factory;

use App\Log\Domain\Entity\LogEntry;
use App\Log\Domain\ValueObject\Client;
use App\Log\Domain\ValueObject\Fingerprint;
use App\Log\Domain\ValueObject\HttpStatus;
use App\Log\Domain\ValueObject\IpAddress;
use App\Log\Domain\ValueObject\Request;
use App\Log\Domain\ValueObject\Tags;
use App\Log\Domain\ValueObject\Uri;
use App\Log\Enum\Environment;
use App\Log\Enum\LogLevel;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Factory de création des LogEntry.
 *
 * Responsabilités :
 * - assembler les ValueObjects
 * - protéger le domaine des payloads hostiles
 * - garantir une création robuste
 * - fournir un point d'entrée unique
 *
 * Cette factory est volontairement explicite.
 *
 * Aucun mapper magique.
 * Aucun hydrateur complexe.
 * Aucun reflection runtime.
 */
final readonly class LogEntryFactory implements LogEntryFactoryInterface
{
    /**
     * Crée un LogEntry depuis un payload normalisé.
     *
     * @param array<string, mixed> $payload
     */
    public function create(
        array $payload,
    ): LogEntry {
        return new LogEntry(
            id: $this->createId(
                $payload,
            ),

            message: $this->createMessage(
                $payload,
            ),

            level: $this->createLevel(
                $payload,
            ),

            domain: $this->createDomain(
                $payload,
            ),

            environment: $this->createEnvironment(
                $payload,
            ),

            httpStatus: HttpStatus::fromExternal(
                $payload['httpStatus'] ?? null,
            ),

            client: Client::fromExternal(
                $payload['client'] ?? null,
            ),

            request: $this->createRequest(
                $payload,
            ),

            ipAddress: IpAddress::fromExternal(
                $payload['ip'] ?? null,
            ),

            fingerprint: Fingerprint::fromExternal(
                $payload['fingerprint'] ?? null,
            ),

            tags: Tags::fromExternal(
                $payload['tags'] ?? [],
            ),

            context: $this->createArray(
                $payload['context'] ?? [],
            ),

            extra: $this->createArray(
                $payload['extra'] ?? [],
            ),

            createdAt: $this->createDate(
                $payload['createdAt'] ?? null,
            ),

            clientDate: $this->createDate(
                $payload['clientDate'] ?? null,
            ),
        );
    }

    /**
     * Crée un UUID stable.
     */
    private function createId(
        array $payload,
    ): string {
        $value = $payload['id'] ?? null;

        if (
            is_string($value)
            && Uuid::isValid($value)
        ) {
            return $value;
        }

        return Uuid::v7()->toRfc4122();
    }

    /**
     * Crée un message sécurisé.
     */
    private function createMessage(
        array $payload,
    ): string {
        $value = $payload['message'] ?? '';

        if (is_string($value) === false) {
            return 'unknown error';
        }

        $value = trim($value);

        if ($value === '') {
            return 'unknown error';
        }

        return mb_substr(
            $value,
            0,
            1000,
        );
    }

    /**
     * Crée un domaine normalisé.
     */
    private function createDomain(
        array $payload,
    ): string {
        $value = $payload['domain'] ?? 'unknown';

        if (is_string($value) === false) {
            return 'unknown';
        }

        $value = strtolower(
            trim($value),
        );

        if ($value === '') {
            return 'unknown';
        }

        return mb_substr(
            $value,
            0,
            100,
        );
    }

    /**
     * Crée un niveau de log robuste.
     */
    private function createLevel(
        array $payload,
    ): LogLevel {
        return LogLevel::fromExternal(
            $payload['level'] ?? null,
        );
    }

    /**
     * Crée un environnement robuste.
     */
    private function createEnvironment(
        array $payload,
    ): Environment {
        return Environment::fromExternal(
            $payload['env'] ?? null,
        );
    }

    /**
     * Crée un Request VO robuste.
     */
    private function createRequest(
        array $payload,
    ): Request {
        return new Request(
            method: $this->createMethod(
                $payload,
            ),

            uri: Uri::fromExternal(
                $payload['uri'] ?? '/',
            ),
        );
    }

    /**
     * Crée une méthode HTTP stable.
     */
    private function createMethod(
        array $payload,
    ): string {
        $value = $payload['method'] ?? 'GET';

        if (is_string($value) === false) {
            return 'GET';
        }

        $value = strtoupper(
            trim($value),
        );

        if ($value === '') {
            return 'GET';
        }

        return mb_substr(
            $value,
            0,
            20,
        );
    }

    /**
     * Crée une date robuste.
     */
    private function createDate(
        mixed $value,
    ): DateTimeImmutable {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if (is_string($value)) {
            try {
                return new DateTimeImmutable(
                    $value,
                );
            } catch (\Throwable) {
            }
        }

        return new DateTimeImmutable();
    }

    /**
     * Garantit un tableau stable.
     *
     * @return array<string, mixed>
     */
    private function createArray(
        mixed $value,
    ): array {
        return is_array($value)
            ? $value
            : [];
    }
}