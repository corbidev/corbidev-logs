<?php

declare(strict_types=1);

namespace App\Log\Application\Factory;

use App\Log\Domain\Entity\LogEntry;
use App\Log\Domain\ValueObject\Client;
use App\Log\Domain\ValueObject\Fingerprint;
use App\Log\Domain\ValueObject\HttpStatus;
use App\Log\Domain\ValueObject\IpAddress;
use App\Log\Domain\ValueObject\Request;
use App\Log\Domain\ValueObject\Uri;
use App\Log\Enum\Environment;
use App\Log\Enum\LogLevel;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Factory de création des LogEntry.
 *
 * RESPONSABILITÉS :
 * -----------------
 * - assembler les ValueObjects
 * - protéger le domaine des payloads hostiles
 * - garantir une création robuste
 * - fournir un point d'entrée unique
 *
 * OBJECTIFS :
 * -----------
 * - robustesse
 * - prédictibilité
 * - stabilité
 * - zéro crash ingestion
 *
 * IMPORTANT :
 * ------------
 * Cette factory ne doit JAMAIS :
 * - throw sur un payload hostile
 * - dépendre d'un mapper magique
 * - dépendre de reflection runtime
 * - dépendre d'hydratation implicite
 *
 * PHILOSOPHIE :
 * -------------
 * Toute donnée externe est hostile.
 *
 * La factory :
 * - nettoie
 * - borne
 * - normalise
 * - stabilise
 *
 * avant d'entrer dans le domaine.
 */
final readonly class LogEntryFactory implements LogEntryFactoryInterface
{
    /**
     * Taille maximale du message.
     */
    private const MAX_MESSAGE_LENGTH = 1000;

    /**
     * Taille maximale du domaine.
     */
    private const MAX_DOMAIN_LENGTH = 100;

    /**
     * Taille maximale de la méthode HTTP.
     */
    private const MAX_METHOD_LENGTH = 20;

    /**
     * Taille maximale du User-Agent.
     */
    private const MAX_USER_AGENT_LENGTH = 500;

    /**
     * Crée un LogEntry depuis un payload externe.
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

            context: $this->createArray(
                $payload['context'] ?? [],
            ),

            extra: $this->createArray(
                $payload['extra'] ?? [],
            ),

            createdAt: $this->createNullableDate(
                $payload['createdAt'] ?? null,
            ),

            clientDate: $this->createNullableDate(
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
     * Crée un message robuste.
     */
    private function createMessage(
        array $payload,
    ): string {
        $value = $payload['message'] ?? null;

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
            self::MAX_MESSAGE_LENGTH,
        );
    }

    /**
     * Crée un domaine stable.
     */
    private function createDomain(
        array $payload,
    ): string {
        $value = $payload['domain'] ?? null;

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
            self::MAX_DOMAIN_LENGTH,
        );
    }

    /**
     * Crée un niveau robuste.
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
     * Crée une Request robuste.
     *
     * IMPORTANT :
     * ------------
     * Cette méthode ne doit jamais :
     * - throw
     * - retourner null
     * - produire une Request invalide
     */
    private function createRequest(
        array $payload,
    ): Request {
        return new Request(
            uri: Uri::fromExternal(
                $payload['uri'] ?? '/',
            ),

            method: $this->createMethod(
                $payload,
            ),

            userAgent: $this->createUserAgent(
                $payload,
            ),
        );
    }

    /**
     * Crée une méthode HTTP stable.
     */
    private function createMethod(
        array $payload,
    ): string {
        $value = $payload['method'] ?? null;

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
            self::MAX_METHOD_LENGTH,
        );
    }

    /**
     * Crée un User-Agent robuste.
     *
     * IMPORTANT :
     * ------------
     * Request attend TOUJOURS une string.
     *
     * Ne jamais retourner null.
     */
    private function createUserAgent(
        array $payload,
    ): string {
        $value = $payload['userAgent'] ?? '';

        if (is_string($value) === false) {
            return '';
        }

        $value = trim($value);

        if ($value === '') {
            return '';
        }

        return mb_substr(
            $value,
            0,
            self::MAX_USER_AGENT_LENGTH,
        );
    }

    /**
     * Crée une date nullable robuste.
     *
     * IMPORTANT :
     * ------------
     * Ne jamais throw.
     */
    private function createNullableDate(
        mixed $value,
    ): ?DateTimeImmutable {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if (is_string($value)) {
            try {
                return new DateTimeImmutable(
                    $value,
                );
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Garantit un tableau stable.
     *
     * IMPORTANT :
     * ------------
     * Ne jamais retourner autre chose
     * qu'un tableau.
     *
     * @return array<string, mixed>
     */
    private function createArray(
        mixed $value,
    ): array {
        if (is_array($value) === false) {
            return [];
        }

        return $value;
    }
}