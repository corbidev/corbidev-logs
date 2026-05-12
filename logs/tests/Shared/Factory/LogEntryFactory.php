<?php

declare(strict_types=1);

namespace App\Tests\Shared\Factory;

use App\Log\Domain\Entity\LogEntry;
use App\Log\Domain\ValueObject\Client;
use App\Log\Domain\ValueObject\Fingerprint;
use App\Log\Domain\ValueObject\HttpStatus;
use App\Log\Domain\ValueObject\IpAddress;
use App\Log\Domain\ValueObject\Request;
use App\Log\Domain\ValueObject\Uri;
use App\Log\Enum\Environment;
use App\Log\Enum\LogLevel;

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
 *
 * - contenir de logique métier
 * - dépendre de Symfony
 * - dépendre de Doctrine
 * - dépendre d'une base SQL
 * - produire de données invalides
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
     * Fingerprint valide par défaut.
     */
    private const DEFAULT_FINGERPRINT = 'a1b2c3d4e5f67890';

    /**
     * Crée un LogEntry valide.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $extra
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
        ?string $fingerprint = null,
        array $context = [],
        array $extra = [],
        ?\DateTimeImmutable $clientDate = null,
        ?\DateTimeImmutable $createdAt = null,
        ?string $id = null,
    ): LogEntry {
        return new LogEntry(
            message: $message,
            level: $level,
            domain: $domain,
            environment: $environment,
            httpStatus: new HttpStatus(
                $httpStatus,
            ),
            client: new Client(
                $client,
            ),
            request: new Request(
                method: $method,
                uri: new Uri(
                    $uri,
                ),
                userAgent: $userAgent,
            ),
            ipAddress: new IpAddress(
                $ip,
            ),
            fingerprint: new Fingerprint(
                $fingerprint
                    ?? self::buildFingerprint(
                        $message,
                        $level,
                        $domain,
                        $environment,
                        $uri,
                    ),
            ),
            context: $context,
            extra: $extra,
            clientDate: $clientDate,
            createdAt: $createdAt,
            id: $id,
        );
    }

    /**
     * Crée un log error standard.
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
     * Crée un log warning standard.
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
     * Crée un log info standard.
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
     * Construit un fingerprint valide.
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
                    $message,
                    $level->value,
                    $domain,
                    $environment->value,
                    $uri,
                ),
            ),
            0,
            16,
        );
    }
}