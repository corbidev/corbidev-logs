<?php

declare(strict_types=1);

namespace App\Log\Application\Factory;

use App\Log\Domain\Entity\LogEntry;
use App\Log\Domain\ValueObject\Client;
use App\Log\Domain\ValueObject\Fingerprint;
use App\Log\Domain\ValueObject\HttpStatus;
use App\Log\Domain\ValueObject\IngestionWarning;
use App\Log\Domain\ValueObject\IpAddress;
use App\Log\Domain\ValueObject\Request;
use App\Log\Domain\ValueObject\RequestId;
use App\Log\Domain\ValueObject\Uri;
use App\Log\Enum\Environment;
use App\Log\Enum\IngestionWarningType;
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
 * - tracer les corrections ingestion
 *
 * OBJECTIFS :
 * -----------
 * - robustesse
 * - prédictibilité
 * - stabilité
 * - zéro crash ingestion
 * - aucune perte
 *
 * IMPORTANT :
 * ------------
 * Cette factory ne doit quasiment
 * jamais throw.
 *
 * Les anomalies ingestion doivent être :
 * - corrigées
 * - stabilisées
 * - tracées via ingestionWarnings
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
     * Taille maximale méthode HTTP.
     */
    private const MAX_METHOD_LENGTH = 10;

    /**
     * Taille maximale User-Agent.
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
        $warnings = [];

        return new LogEntry(
            id: $this->createId(
                $payload,
            ),

            message: $this->createMessage(
                $payload,
                $warnings,
            ),

            level: $this->createLevel(
                $payload,
                $warnings,
            ),

            domain: $this->createDomain(
                $payload,
                $warnings,
            ),

            environment: $this->createEnvironment(
                $payload,
                $warnings,
            ),

            httpStatus: $this->createHttpStatus(
                $payload,
                $warnings,
            ),

            client: $this->createClient(
                $payload,
                $warnings,
            ),

            request: $this->createRequest(
                $payload,
                $warnings,
            ),

            ipAddress: $this->createIpAddress(
                $payload,
                $warnings,
            ),

            fingerprint: $this->createFingerprint(
                $payload,
                $warnings,
            ),

            requestId: $this->createRequestId(
                $payload,
                $warnings,
            ),

            ingestionWarnings: $warnings,

            context: $this->createArray(
                $payload['context'] ?? [],
            ),

            extra: $this->createArray(
                $payload['extra'] ?? [],
            ),

            createdAt: $this->createNullableDate(
                $payload['createdAt'] ?? null,
                'createdAt',
                $warnings,
            ),

            clientDate: $this->createNullableDate(
                $payload['clientDate'] ?? null,
                'clientDate',
                $warnings,
            ),
        );
    }

    /**
     * Crée un UUID stable.
     *
     * @param array<string, mixed> $payload
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
     *
     * @param list<IngestionWarning> $warnings
     */
    private function createMessage(
        array $payload,
        array &$warnings,
    ): string {
        $value = $payload['message'] ?? null;

        if (is_string($value) === false) {
            $this->addWarning(
                warnings: $warnings,
                field: 'message',
                type: IngestionWarningType::INVALID_MESSAGE,
                original: $value,
                fallback: 'unknown error',
            );

            return 'unknown error';
        }

        $value = trim(
            $value,
        );

        if ($value === '') {
            return 'unknown error';
        }

        if (
            mb_strlen($value)
            <= self::MAX_MESSAGE_LENGTH
        ) {
            return $value;
        }

        $this->addWarning(
            warnings: $warnings,
            field: 'message',
            type: IngestionWarningType::MESSAGE_TRUNCATED,
            original: mb_strlen($value),
            fallback: self::MAX_MESSAGE_LENGTH,
        );

        return mb_substr(
            $value,
            0,
            self::MAX_MESSAGE_LENGTH,
        );
    }

    /**
     * Crée un domaine stable.
     *
     * @param list<IngestionWarning> $warnings
     */
    private function createDomain(
        array $payload,
        array &$warnings,
    ): string {
        $value = $payload['domain'] ?? null;

        if (is_string($value) === false) {
            return 'unknown';
        }

        $original = $value;

        $value = strtolower(
            trim($value),
        );

        if ($value === '') {
            return 'unknown';
        }

        if (
            mb_strlen($value)
            <= self::MAX_DOMAIN_LENGTH
        ) {
            return $value;
        }

        $normalized = mb_substr(
            $value,
            0,
            self::MAX_DOMAIN_LENGTH,
        );

        $this->addWarning(
            warnings: $warnings,
            field: 'domain',
            type: IngestionWarningType::DOMAIN_NORMALIZED,
            original: $original,
            fallback: $normalized,
        );

        return $normalized;
    }

    /**
     * Crée un niveau robuste.
     *
     * @param list<IngestionWarning> $warnings
     */
    private function createLevel(
        array $payload,
        array &$warnings,
    ): LogLevel {
        $value = $payload['level'] ?? null;

        try {
            return LogLevel::fromExternal(
                $value,
            );
        } catch (\Throwable) {
            $this->addWarning(
                warnings: $warnings,
                field: 'level',
                type: IngestionWarningType::INVALID_LEVEL,
                original: $value,
                fallback: LogLevel::ERROR->value,
            );

            return LogLevel::ERROR;
        }
    }

    /**
     * Crée un environnement robuste.
     *
     * @param list<IngestionWarning> $warnings
     */
    private function createEnvironment(
        array $payload,
        array &$warnings,
    ): Environment {
        $value = $payload['env'] ?? null;

        try {
            return Environment::fromExternal(
                $value,
            );
        } catch (\Throwable) {
            $this->addWarning(
                warnings: $warnings,
                field: 'env',
                type: IngestionWarningType::INVALID_ENVIRONMENT,
                original: $value,
                fallback: Environment::Production->value,
            );

            return Environment::Production;
        }
    }

    /**
     * Crée un status HTTP robuste.
     *
     * @param list<IngestionWarning> $warnings
     */
    private function createHttpStatus(
        array $payload,
        array &$warnings,
    ): HttpStatus {
        $value = $payload['httpStatus'] ?? null;

        try {
            return HttpStatus::fromExternal(
                $value,
            );
        } catch (\Throwable) {
            $this->addWarning(
                warnings: $warnings,
                field: 'httpStatus',
                type: IngestionWarningType::INVALID_HTTP_STATUS,
                original: $value,
                fallback: 500,
            );

            return new HttpStatus(
                500,
            );
        }
    }

    /**
     * Crée un client robuste.
     *
     * @param list<IngestionWarning> $warnings
     */
    private function createClient(
        array $payload,
        array &$warnings,
    ): Client {
        $value = $payload['client'] ?? null;

        try {
            return Client::fromExternal(
                $value,
            );
        } catch (\Throwable) {
            $this->addWarning(
                warnings: $warnings,
                field: 'client',
                type: IngestionWarningType::INVALID_CLIENT,
                original: $value,
                fallback: 'unknown',
            );

            return new Client(
                'unknown',
            );
        }
    }

    /**
     * Crée une Request robuste.
     *
     * @param list<IngestionWarning> $warnings
     */
    private function createRequest(
        array $payload,
        array &$warnings,
    ): Request {
        $requestPayload = $payload['request'] ?? [];

        if (is_array($requestPayload) === false) {
            $requestPayload = [];
        }

        return new Request(
            uri: $this->createUri(
                $requestPayload,
                $payload,
                $warnings,
            ),

            method: $this->createMethod(
                $requestPayload,
                $payload,
                $warnings,
            ),

            userAgent: $this->createUserAgent(
                $requestPayload,
                $payload,
                $warnings,
            ),
        );
    }

    /**
     * Crée une URI robuste.
     *
     * @param list<IngestionWarning> $warnings
     */
    private function createUri(
        array $requestPayload,
        array $payload,
        array &$warnings,
    ): Uri {
        $value = $requestPayload['uri']
            ?? $payload['uri']
            ?? '/';

        try {
            return Uri::fromExternal(
                $value,
            );
        } catch (\Throwable) {
            $this->addWarning(
                warnings: $warnings,
                field: 'uri',
                type: IngestionWarningType::INVALID_URI,
                original: $value,
                fallback: '/',
            );

            return new Uri(
                '/',
            );
        }
    }

    /**
     * Crée une méthode HTTP stable.
     *
     * @param list<IngestionWarning> $warnings
     */
    private function createMethod(
        array $requestPayload,
        array $payload,
        array &$warnings,
    ): string {
        $value = $requestPayload['method']
            ?? $payload['method']
            ?? null;

        if (is_string($value) === false) {
            return 'GET';
        }

        $original = $value;

        $value = strtoupper(
            trim($value),
        );

        $value = preg_replace(
            '/[^A-Z]/',
            '',
            $value,
        );

        if ($value === null || $value === '') {
            $this->addWarning(
                warnings: $warnings,
                field: 'method',
                type: IngestionWarningType::INVALID_METHOD,
                original: $original,
                fallback: 'GET',
            );

            return 'GET';
        }

        if (
            mb_strlen($value)
            > self::MAX_METHOD_LENGTH
        ) {
            $truncated = mb_substr(
                $value,
                0,
                self::MAX_METHOD_LENGTH,
            );

            $this->addWarning(
                warnings: $warnings,
                field: 'method',
                type: IngestionWarningType::METHOD_TRUNCATED,
                original: $original,
                fallback: $truncated,
            );

            $value = $truncated;
        }

        return match ($value) {
            'GET',
            'POST',
            'PUT',
            'PATCH',
            'DELETE',
            'HEAD',
            'OPTIONS' => $value,

            default => $this->fallbackMethod(
                original: $original,
                warnings: $warnings,
            ),
        };
    }

    /**
     * Crée un User-Agent robuste.
     *
     * @param list<IngestionWarning> $warnings
     */
    private function createUserAgent(
        array $requestPayload,
        array $payload,
        array &$warnings,
    ): string {
        $value = $requestPayload['userAgent']
            ?? $payload['userAgent']
            ?? '';

        if (is_string($value) === false) {
            return '';
        }

        $value = trim(
            $value,
        );

        if ($value === '') {
            return '';
        }

        if (
            mb_strlen($value)
            <= self::MAX_USER_AGENT_LENGTH
        ) {
            return $value;
        }

        $this->addWarning(
            warnings: $warnings,
            field: 'userAgent',
            type: IngestionWarningType::USER_AGENT_TRUNCATED,
            original: mb_strlen($value),
            fallback: self::MAX_USER_AGENT_LENGTH,
        );

        return mb_substr(
            $value,
            0,
            self::MAX_USER_AGENT_LENGTH,
        );
    }

    /**
     * Crée une IP robuste.
     *
     * @param list<IngestionWarning> $warnings
     */
    private function createIpAddress(
        array $payload,
        array &$warnings,
    ): IpAddress {
        $value = $payload['ip'] ?? null;

        try {
            return IpAddress::fromExternal(
                $value,
            );
        } catch (\Throwable) {
            $this->addWarning(
                warnings: $warnings,
                field: 'ip',
                type: IngestionWarningType::INVALID_IP,
                original: $value,
                fallback: '0.0.0.0',
            );

            return new IpAddress(
                '0.0.0.0',
            );
        }
    }

    /**
     * Crée un fingerprint robuste.
     *
     * @param list<IngestionWarning> $warnings
     */
    private function createFingerprint(
        array $payload,
        array &$warnings,
    ): Fingerprint {
        $value = $payload['fingerprint'] ?? null;

        try {
            return Fingerprint::fromExternal(
                $value,
            );
        } catch (\Throwable) {
            $generated = substr(
                sha1(
                    sprintf(
                        '%s|%s|%s',
                        (string) ($payload['message'] ?? ''),
                        (string) ($payload['domain'] ?? ''),
                        microtime(true),
                    ),
                ),
                0,
                16,
            );

            $this->addWarning(
                warnings: $warnings,
                field: 'fingerprint',
                type: IngestionWarningType::FINGERPRINT_REGENERATED,
                original: $value,
                fallback: $generated,
            );

            return new Fingerprint(
                $generated,
            );
        }
    }

    /**
     * Crée un RequestId robuste.
     *
     * @param list<IngestionWarning> $warnings
     */
    private function createRequestId(
        array $payload,
        array &$warnings,
    ): RequestId {
        $value = $payload['requestId'] ?? null;

        try {
            return RequestId::fromNullable(
                $value,
            );
        } catch (\Throwable) {
            $generated = RequestId::generate();

            $this->addWarning(
                warnings: $warnings,
                field: 'requestId',
                type: IngestionWarningType::INVALID_REQUEST_ID,
                original: $value,
                fallback: $generated->value(),
            );

            return $generated;
        }
    }

    /**
     * Crée une date nullable robuste.
     *
     * @param list<IngestionWarning> $warnings
     */
    private function createNullableDate(
        mixed $value,
        string $field,
        array &$warnings,
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
                $this->addWarning(
                    warnings: $warnings,
                    field: $field,
                    type: $field === 'createdAt'
                        ? IngestionWarningType::INVALID_CREATED_AT
                        : IngestionWarningType::INVALID_CLIENT_DATE,
                    original: $value,
                    fallback: null,
                );

                return null;
            }
        }

        return null;
    }

    /**
     * Garantit un tableau stable.
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

    /**
     * Fallback méthode HTTP.
     *
     * @param list<IngestionWarning> $warnings
     */
    private function fallbackMethod(
        mixed $original,
        array &$warnings,
    ): string {
        $this->addWarning(
            warnings: $warnings,
            field: 'method',
            type: IngestionWarningType::INVALID_METHOD,
            original: $original,
            fallback: 'GET',
        );

        return 'GET';
    }

    /**
     * Ajoute un warning ingestion.
     *
     * @param list<IngestionWarning> $warnings
     */
    private function addWarning(
        array &$warnings,
        string $field,
        IngestionWarningType $type,
        mixed $original,
        mixed $fallback,
    ): void {
        $warnings[] = new IngestionWarning(
            field: $field,
            type: $type,
            original: $original,
            fallback: $fallback,
        );
    }
}