<?php

declare(strict_types=1);

namespace App\Tests\Shared\Factory;

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
 * Factory centralisée de LogEntry pour les tests.
 *
 * OBJECTIFS :
 * -----------
 * - éviter la duplication
 * - stabiliser les tests
 * - centraliser les invariants
 * - produire des LogEntry toujours valides
 * - fournir une base fiable pour les crash tests
 *
 * IMPORTANT :
 * ------------
 * Cette factory est STRICTEMENT technique.
 *
 * Elle ne doit jamais :
 * - contenir de logique métier
 * - dépendre de Symfony runtime
 * - dépendre de Doctrine
 * - dépendre d'une base SQL
 * - produire de données invalides
 *
 * PHILOSOPHIE :
 * -------------
 * Cette factory doit refléter
 * l'architecture réelle du projet :
 *
 * - robustesse
 * - normalisation
 * - stabilité
 * - jamais de crash sur payload hostile
 *
 * Toute évolution du constructeur
 * de LogEntry doit être centralisée ici.
 */
final class LogEntryFactory
{
    /**
     * Domaine par défaut.
     */
    private const DEFAULT_DOMAIN = 'app';

    /**
     * URI par défaut.
     */
    private const DEFAULT_URI = '/test';

    /**
     * User-Agent par défaut.
     */
    private const DEFAULT_USER_AGENT = 'PHPUnit';

    /**
     * Adresse IP par défaut.
     */
    private const DEFAULT_IP = '127.0.0.1';

    /**
     * Client par défaut.
     */
    private const DEFAULT_CLIENT = 'phpunit';

    /**
     * Message par défaut.
     */
    private const DEFAULT_MESSAGE = 'Test log entry';

    /**
     * RequestId par défaut.
     */
    private const DEFAULT_REQUEST_ID = 'req_phpunit_test';

    /**
     * Fingerprint valide par défaut.
     */
    private const DEFAULT_FINGERPRINT = 'a1b2c3d4e5f67890';

    /**
     * Taille maximale du message.
     */
    private const MAX_MESSAGE_LENGTH = 1000;

    /**
     * Taille maximale User-Agent.
     */
    private const MAX_USER_AGENT_LENGTH = 500;

    /**
     * Taille maximale méthode HTTP.
     */
    private const MAX_METHOD_LENGTH = 10;

    /**
     * Taille maximale domaine.
     */
    private const MAX_DOMAIN_LENGTH = 100;

    /**
     * Crée un LogEntry valide.
     *
     * @param list<IngestionWarning> $ingestionWarnings
     * @param array<string, mixed>   $context
     * @param array<string, mixed>   $extra
     */
    public static function create(
        string $message = self::DEFAULT_MESSAGE,
        LogLevel $level = LogLevel::ERROR,
        string $domain = self::DEFAULT_DOMAIN,
        Environment $environment = Environment::Test,
        int $httpStatus = 500,
        string $client = self::DEFAULT_CLIENT,
        string $method = 'GET',
        string $uri = self::DEFAULT_URI,
        string $userAgent = self::DEFAULT_USER_AGENT,
        string $ip = self::DEFAULT_IP,
        ?string $requestId = null,
        ?string $fingerprint = null,
        array $ingestionWarnings = [],
        array $context = [],
        array $extra = [],
        ?DateTimeImmutable $clientDate = null,
        ?DateTimeImmutable $createdAt = null,
        ?string $id = null,
    ): LogEntry {
        $warnings = self::normalizeWarnings(
            $ingestionWarnings,
        );

        $normalizedMessage = self::normalizeMessage(
            $message,
            $warnings,
        );

        $normalizedDomain = self::normalizeDomain(
            $domain,
        );

        $normalizedMethod = self::normalizeMethod(
            $method,
        );

        $normalizedUserAgent = self::normalizeUserAgent(
            $userAgent,
            $warnings,
        );

        return new LogEntry(
            message: $normalizedMessage,

            level: $level,

            domain: $normalizedDomain,

            environment: $environment,

            httpStatus: new HttpStatus(
                $httpStatus,
            ),

            client: new Client(
                $client,
            ),

            requestId: new RequestId(
                $requestId
                    ?? self::DEFAULT_REQUEST_ID,
            ),

            request: new Request(
                method: $normalizedMethod,

                uri: new Uri(
                    $uri,
                ),

                userAgent: $normalizedUserAgent,
            ),

            ipAddress: new IpAddress(
                $ip,
            ),

            fingerprint: new Fingerprint(
                $fingerprint
                    ?? self::buildFingerprint(
                        $normalizedMessage,
                        $level,
                        $normalizedDomain,
                        $environment,
                        $uri,
                    ),
            ),

            ingestionWarnings: $warnings,

            context: self::normalizeArray(
                $context,
            ),

            extra: self::normalizeArray(
                $extra,
            ),

            clientDate: $clientDate,

            createdAt: $createdAt
                ?? new DateTimeImmutable(),

            externalId: self::normalizeId(
                $id,
            ),
        );
    }

    /**
     * Crée un log ERROR standard.
     */
    public static function error(
        string $message = self::DEFAULT_MESSAGE,
    ): LogEntry {
        return self::create(
            message: $message,
            level: LogLevel::ERROR,
            httpStatus: 500,
        );
    }

    /**
     * Crée un log WARNING standard.
     */
    public static function warning(
        string $message = self::DEFAULT_MESSAGE,
    ): LogEntry {
        return self::create(
            message: $message,
            level: LogLevel::WARNING,
            httpStatus: 400,
        );
    }

    /**
     * Crée un log INFO standard.
     */
    public static function info(
        string $message = self::DEFAULT_MESSAGE,
    ): LogEntry {
        return self::create(
            message: $message,
            level: LogLevel::INFO,
            httpStatus: 200,
        );
    }

    /**
     * Crée plusieurs LogEntry.
     *
     * @return list<LogEntry>
     */
    public static function many(
        int $count,
    ): array {
        $count = max(
            0,
            $count,
        );

        $entries = [];

        for ($i = 0; $i < $count; ++$i) {
            $entries[] = self::create(
                message: sprintf(
                    'Test log #%d',
                    $i,
                ),

                requestId: sprintf(
                    'req_test_%d',
                    $i,
                ),

                fingerprint: substr(
                    sha1(
                        sprintf(
                            'log-entry-%d',
                            $i,
                        ),
                    ),
                    0,
                    16,
                ),
            );
        }

        return $entries;
    }

    /**
     * Normalise un message.
     *
     * IMPORTANT :
     * ------------
     * - jamais vide
     * - jamais null
     * - jamais d'exception
     *
     * @param list<IngestionWarning> $warnings
     */
    private static function normalizeMessage(
        string $message,
        array &$warnings,
    ): string {
        $message = trim(
            $message,
        );

        if ($message === '') {
            return self::DEFAULT_MESSAGE;
        }

        if (
            mb_strlen($message)
            <= self::MAX_MESSAGE_LENGTH
        ) {
            return $message;
        }

        $warnings[] = new IngestionWarning(
            field: 'message',
            type: IngestionWarningType::MESSAGE_TRUNCATED,
            original: mb_strlen($message),
            fallback: self::MAX_MESSAGE_LENGTH,
        );

        return mb_substr(
            $message,
            0,
            self::MAX_MESSAGE_LENGTH,
        );
    }

    /**
     * Normalise un domaine.
     */
    private static function normalizeDomain(
        string $domain,
    ): string {
        $domain = strtolower(
            trim($domain),
        );

        if ($domain === '') {
            return self::DEFAULT_DOMAIN;
        }

        return mb_substr(
            $domain,
            0,
            self::MAX_DOMAIN_LENGTH,
        );
    }

    /**
     * Normalise une méthode HTTP.
     *
     * IMPORTANT :
     * ------------
     * - jamais vide
     * - toujours stable
     * - jamais d'exception
     */
    private static function normalizeMethod(
        string $method,
    ): string {
        $method = strtoupper(
            trim($method),
        );

        $method = preg_replace(
            '/[^A-Z]/',
            '',
            $method,
        );

        if ($method === null) {
            return 'GET';
        }

        if ($method === '') {
            return 'GET';
        }

        $method = mb_substr(
            $method,
            0,
            self::MAX_METHOD_LENGTH,
        );

        return match ($method) {
            'GET',
            'POST',
            'PUT',
            'PATCH',
            'DELETE',
            'HEAD',
            'OPTIONS' => $method,

            default => 'GET',
        };
    }

    /**
     * Normalise un User-Agent.
     *
     * IMPORTANT :
     * ------------
     * - jamais null
     * - jamais d'exception
     *
     * @param list<IngestionWarning> $warnings
     */
    private static function normalizeUserAgent(
        string $userAgent,
        array &$warnings,
    ): string {
        $userAgent = trim(
            $userAgent,
        );

        if ($userAgent === '') {
            return '';
        }

        if (
            mb_strlen($userAgent)
            <= self::MAX_USER_AGENT_LENGTH
        ) {
            return $userAgent;
        }

        $warnings[] = new IngestionWarning(
            field: 'userAgent',
            type: IngestionWarningType::USER_AGENT_TRUNCATED,
            original: mb_strlen($userAgent),
            fallback: self::MAX_USER_AGENT_LENGTH,
        );

        return mb_substr(
            $userAgent,
            0,
            self::MAX_USER_AGENT_LENGTH,
        );
    }

    /**
     * Normalise les warnings ingestion.
     *
     * @param list<IngestionWarning> $warnings
     *
     * @return list<IngestionWarning>
     */
    private static function normalizeWarnings(
        array $warnings,
    ): array {
        $normalized = [];

        foreach ($warnings as $warning) {
            if ($warning instanceof IngestionWarning) {
                $normalized[] = $warning;
            }
        }

        return $normalized;
    }

    /**
     * Garantit un tableau stable.
     *
     * @param array<string, mixed> $value
     *
     * @return array<string, mixed>
     */
    private static function normalizeArray(
        array $value,
    ): array {
        return $value;
    }

    /**
     * Normalise un identifiant.
     */
    private static function normalizeId(
        ?string $id,
    ): string {
        if ($id === null) {
            return Uuid::v7()->toRfc4122();
        }

        $id = trim(
            $id,
        );

        if ($id === '') {
            return Uuid::v7()->toRfc4122();
        }

        return $id;
    }

    /**
     * Construit un fingerprint valide.
     *
     * IMPORTANT :
     * ------------
     * Format :
     * - sha1 tronqué
     * - lowercase
     * - 16 caractères
     */
    private static function buildFingerprint(
        string $message,
        LogLevel $level,
        string $domain,
        Environment $environment,
        string $uri,
    ): string {
        return substr(
            sha1(
                sprintf(
                    '%s|%s|%s|%s|%s',
                    strtolower(
                        trim($message),
                    ),

                    $level->value,

                    strtolower(
                        trim($domain),
                    ),

                    $environment->value,

                    strtok(
                        $uri,
                        '?',
                    ) ?: '/',
                ),
            ),
            0,
            16,
        );
    }

    /**
     * Crée un warning ingestion standard.
     */
    public static function warningMessageTruncated(): IngestionWarning
    {
        return new IngestionWarning(
            field: 'message',
            type: IngestionWarningType::MESSAGE_TRUNCATED,
            original: 5000,
            fallback: 1000,
        );
    }

    /**
     * Crée un warning IP invalide.
     */
    public static function warningInvalidIp(): IngestionWarning
    {
        return new IngestionWarning(
            field: 'ip',
            type: IngestionWarningType::INVALID_IP,
            original: '999.999.999.999',
            fallback: '127.0.0.1',
        );
    }
}