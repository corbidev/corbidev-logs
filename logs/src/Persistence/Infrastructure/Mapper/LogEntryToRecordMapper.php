<?php

declare(strict_types=1);

namespace App\Persistence\Infrastructure\Mapper;

use App\Log\Domain\Entity\LogEntry;
use App\Persistence\Infrastructure\Entity\LogRecord;

/**
 * Mapper explicite Domain → Persistence.
 *
 * RESPONSABILITÉS :
 * -----------------
 * - transformer LogEntry vers LogRecord
 * - protéger la couche SQL
 * - garantir des données persistables
 * - sécuriser UTF-8
 * - borner les tailles SQL
 * - normaliser les payloads JSON
 * - préserver les warnings ingestion
 *
 * IMPORTANT :
 * ------------
 * Ce mapper ne doit JAMAIS :
 * - faire de persistence
 * - utiliser Doctrine directement
 * - contenir de logique métier
 * - recalculer le fingerprint
 * - modifier les invariants métier
 * - throw sur données hostiles
 *
 * PHILOSOPHIE :
 * -------------
 * Toute donnée externe est hostile.
 *
 * Le mapper agit comme :
 *
 * LogEntry
 *     ↓
 * sanitation SQL
 *     ↓
 * LogRecord
 */
final class LogEntryToRecordMapper
{
    /**
     * Taille maximale SQL.
     */
    private const DOMAIN_MAX_LENGTH = 255;

    private const URI_MAX_LENGTH = 1000;

    private const METHOD_MAX_LENGTH = 20;

    private const USER_AGENT_MAX_LENGTH = 500;

    private const ENV_MAX_LENGTH = 50;

    private const CLIENT_MAX_LENGTH = 50;

    private const LEVEL_MAX_LENGTH = 20;

    private const FINGERPRINT_MAX_LENGTH = 16;

    private const REQUEST_ID_MAX_LENGTH = 100;

    private const EXTERNAL_ID_MAX_LENGTH = 36;

    private const IP_MAX_LENGTH = 45;

    /**
     * Limites JSON.
     */
    private const MAX_JSON_DEPTH = 5;

    private const MAX_JSON_ITEMS = 50;

    private const MAX_STRING_LENGTH = 1000;

    /**
     * Taille maximale clé JSON.
     */
    private const MAX_JSON_KEY_LENGTH = 100;

    /**
     * Mappe un LogEntry vers LogRecord.
     */
    public function map(
        int $projectId,
        LogEntry $entry,
    ): LogRecord {
        $record = new LogRecord();

        $record->setExternalId(
            $this->truncate(
                $this->sanitizeString(
                    $entry->id(),
                ),
                self::EXTERNAL_ID_MAX_LENGTH,
            ),
        );

        $record->setProjectId(
            (string) $projectId,
        );

        $record->setFingerprint(
            $this->truncate(
                $this->sanitizeString(
                    $entry
                        ->fingerprint()
                        ->value(),
                ),
                self::FINGERPRINT_MAX_LENGTH,
            ),
        );

        $record->setRequestId(
            $this->truncate(
                $this->sanitizeString(
                    $entry
                        ->requestId()
                        ->value(),
                ),
                self::REQUEST_ID_MAX_LENGTH,
            ),
        );

        $record->setLevel(
            $this->truncate(
                $this->sanitizeString(
                    $entry
                        ->level()
                        ->value,
                ),
                self::LEVEL_MAX_LENGTH,
            ),
        );

        $record->setHttpStatus(
            $entry
                ->httpStatus()
                ->value(),
        );

        $record->setDomain(
            $this->truncate(
                $this->sanitizeString(
                    $entry->domain(),
                ),
                self::DOMAIN_MAX_LENGTH,
            ),
        );

        $record->setUri(
            $this->truncate(
                $this->sanitizeString(
                    $entry
                        ->request()
                        ->uri()
                        ->value(),
                ),
                self::URI_MAX_LENGTH,
            ),
        );

        $record->setMethod(
            $this->createNullableString(
                value: $entry
                    ->request()
                    ->method(),
                maxLength: self::METHOD_MAX_LENGTH,
            ),
        );

        $record->setUserAgent(
            $this->createNullableString(
                value: $entry
                    ->request()
                    ->userAgent(),
                maxLength: self::USER_AGENT_MAX_LENGTH,
            ),
        );

        $record->setEnv(
            $this->truncate(
                $this->sanitizeString(
                    $entry
                        ->environment()
                        ->value,
                ),
                self::ENV_MAX_LENGTH,
            ),
        );

        $record->setClient(
            $this->truncate(
                $this->sanitizeString(
                    $entry
                        ->client()
                        ->value(),
                ),
                self::CLIENT_MAX_LENGTH,
            ),
        );

        $record->setMessage(
            $this->sanitizeString(
                $entry->message(),
            ),
        );

        $record->setContextJson(
            $this->normalizeContext(
                $entry->context(),
            ),
        );

        $record->setExtraJson(
            $this->normalizeContext(
                $entry->extra(),
            ),
        );

        $record->setIngestionWarningsJson(
            $this->normalizeWarnings(
                $entry->ingestionWarnings(),
            ),
        );

        $record->setCreatedAt(
            $entry->createdAt(),
        );

        $record->setClientDate(
            $entry->clientDate(),
        );

        $record->setIp(
            $this->createNullableString(
                value: $entry
                    ->ipAddress()
                    ->value(),
                maxLength: self::IP_MAX_LENGTH,
            ),
        );

        return $record;
    }

    /**
     * Crée une string nullable persistable.
     */
    private function createNullableString(
        string $value,
        int $maxLength,
    ): ?string {
        $value = $this->truncate(
            $this->sanitizeString(
                $value,
            ),
            $maxLength,
        );

        if ($value === '') {
            return null;
        }

        return $value;
    }

    /**
     * Nettoie une string avant persistence SQL.
     *
     * IMPORTANT :
     * ------------
     * - UTF-8 safe
     * - suppression caractères contrôle
     * - jamais d'exception
     * - toujours string
     */
    private function sanitizeString(
        mixed $value,
    ): string {
        if (
            !is_scalar($value)
            && !$value instanceof \Stringable
        ) {
            return '';
        }

        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        /**
         * Suppression bytes NULL.
         */
        $value = str_replace(
            "\0",
            '',
            $value,
        );

        /**
         * Protection UTF-8.
         */
        if (
            !mb_check_encoding(
                $value,
                'UTF-8',
            )
        ) {
            $converted = @mb_convert_encoding(
                $value,
                'UTF-8',
                'UTF-8',
            );

            if (
                is_string($converted)
            ) {
                $value = $converted;
            }
        }

        /**
         * Suppression caractères contrôle.
         */
        $value = preg_replace(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
            '',
            $value,
        );

        if ($value === null) {
            return '';
        }

        return trim(
            $value,
        );
    }

    /**
     * Tronque une string pour sécurité SQL.
     */
    private function truncate(
        string $value,
        int $maxLength,
    ): string {
        if (
            mb_strlen($value)
            <= $maxLength
        ) {
            return $value;
        }

        return mb_substr(
            $value,
            0,
            $maxLength,
        );
    }

    /**
     * Normalise les warnings ingestion.
     *
     * @param array<mixed> $warnings
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeWarnings(
        array $warnings,
    ): array {
        $normalized = [];

        foreach ($warnings as $warning) {
            if ($warning instanceof \App\Log\Domain\ValueObject\IngestionWarning) {
                $warning = $warning->toArray();
            }

            if (
                !is_array($warning)
            ) {
                continue;
            }

            $normalized[] = $this->normalizeArray(
                data: $warning,
                depth: 0,
            );
        }

        return $normalized;
    }

    /**
     * Normalise récursivement le contexte JSON.
     *
     * OBJECTIFS :
     * -----------
     * - UTF-8 safe
     * - profondeur bornée
     * - mémoire bornée
     * - données sérialisables
     *
     * @param array<mixed> $context
     *
     * @return array<string|int, mixed>
     */
    private function normalizeContext(
        array $context,
    ): array {
        return $this->normalizeArray(
            data: $context,
            depth: 0,
        );
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<string|int, mixed>
     */
    private function normalizeArray(
        array $data,
        int $depth,
    ): array {
        if (
            $depth
            >= self::MAX_JSON_DEPTH
        ) {
            return [
                '__truncated__'
                    => 'max_depth_reached',
            ];
        }

        $normalized = [];

        $count = 0;

        foreach ($data as $key => $value) {
            ++$count;

            if (
                $count
                > self::MAX_JSON_ITEMS
            ) {
                $normalized['__truncated__']
                    = 'max_items_reached';

                break;
            }

            $normalizedKey = $this->normalizeKey(
                $key,
            );

            $normalized[$normalizedKey]
                = $this->normalizeValue(
                    value: $value,
                    depth: $depth,
                );
        }

        return $normalized;
    }

    /**
     * Normalise une clé JSON.
     */
    private function normalizeKey(
        mixed $key,
    ): string|int {
        if (!is_string($key)) {
            return (int) $key;
        }

        $key = $this->truncate(
            $this->sanitizeString(
                $key,
            ),
            self::MAX_JSON_KEY_LENGTH,
        );

        if ($key === '') {
            return 'unknown';
        }

        return $key;
    }

    /**
     * Normalise une valeur JSON.
     */
    private function normalizeValue(
        mixed $value,
        int $depth,
    ): mixed {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            if (
                is_nan($value)
                || is_infinite($value)
            ) {
                return null;
            }

            return $value;
        }

        if (is_string($value)) {
            return $this->truncate(
                $this->sanitizeString(
                    $value,
                ),
                self::MAX_STRING_LENGTH,
            );
        }

        if ($value instanceof \Stringable) {
            return $this->truncate(
                $this->sanitizeString(
                    (string) $value,
                ),
                self::MAX_STRING_LENGTH,
            );
        }

        if (
            $value
            instanceof \DateTimeInterface
        ) {
            return $value->format(
                \DateTimeInterface::ATOM,
            );
        }

        if (is_array($value)) {
            return $this->normalizeArray(
                data: $value,
                depth: $depth + 1,
            );
        }

        /**
         * Ressources interdites.
         */
        if (is_resource($value)) {
            return '[resource]';
        }

        /**
         * Objets interdits.
         */
        if (is_object($value)) {
            return sprintf(
                '[object:%s]',
                $value::class,
            );
        }

        return null;
    }
}
