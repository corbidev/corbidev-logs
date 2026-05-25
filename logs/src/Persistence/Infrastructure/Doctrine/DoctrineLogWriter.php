<?php

declare(strict_types=1);

namespace App\Persistence\Infrastructure\Doctrine;

use App\Log\Domain\Entity\LogEntry;
use App\Log\Domain\ValueObject\IngestionWarning;
use App\Persistence\Domain\LogWriterInterface;
use App\Persistence\Domain\PersistenceResult;
use App\Persistence\Infrastructure\Mapper\LogEntryToRecordMapper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;

/**
 * Writer Doctrine robuste pour la persistence SQL.
 *
 * RESPONSABILITÉS :
 * -----------------
 * - persister les LogEntry
 * - isoler les erreurs SQL
 * - protéger le pipeline de persistence
 * - garantir des résultats explicites
 *
 * CONTRAINTES :
 * -------------
 * - SQL explicite uniquement
 * - aucun ORM
 * - aucune logique métier
 * - mémoire bornée
 * - batch borné
 * - aucune dépendance au state interne
 *
 * PHILOSOPHIE :
 * -------------
 * Le writer ne doit jamais casser
 * le pipeline global.
 *
 * Toute erreur :
 * - est capturée
 * - est normalisée
 * - est journalisée
 * - reste bornée
 */
final readonly class DoctrineLogWriter implements LogWriterInterface
{
    /**
     * Taille maximale d'un batch.
     */
    private const int MAX_BATCH_SIZE = 500;

    /**
     * Nom de la table SQL.
     */
    private const string TABLE_NAME = 'logs';

    /**
     * Projet par défaut en attendant le binding auth/project.
     */
    private const int DEFAULT_PROJECT_ID = 1;

    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger,
        private ?LogEntryToRecordMapper $mapper = null,
    ) {}

    /**
     * Persiste une liste de logs.
     *
     * @param list<LogEntry> $entries
     */
    public function persist(
        array $entries,
    ): PersistenceResult {
        if ([] === $entries) {
            return PersistenceResult::nothingToPersist();
        }

        $entries = $this->limitBatchSize(
            $entries,
        );

        $persistedCount = 0;
        $failedCount = 0;

        $errors = [];

        try {
            $this->connection->beginTransaction();

            foreach ($entries as $entry) {
                try {
                    $this->insertLogEntry(
                        $entry,
                    );

                    ++$persistedCount;
                } catch (\Throwable $exception) {
                    ++$failedCount;

                    $errors[] = $this->buildErrorMessage(
                        $entry,
                        $exception,
                    );

                    $this->logInsertionFailure(
                        $entry,
                        $exception,
                    );
                }
            }

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->safeRollback();

            $errors[] = sprintf(
                'Persistence batch failure: %s',
                $exception->getMessage(),
            );

            $this->logger->critical(
                'Persistence batch failure.',
                [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ],
            );

            return PersistenceResult::failure(
                failedCount: count($entries),
                errors: $errors,
            );
        }

        if (
            $persistedCount > 0
            && $failedCount === 0
        ) {
            return PersistenceResult::success(
                persistedCount: $persistedCount,
            );
        }

        if (
            $persistedCount === 0
            && $failedCount > 0
        ) {
            return PersistenceResult::failure(
                failedCount: $failedCount,
                errors: $errors,
            );
        }

        return PersistenceResult::partial(
            persistedCount: $persistedCount,
            failedCount: $failedCount,
            errors: $errors,
        );
    }

    /**
     * Insère un LogEntry.
     *
     * @throws Exception
     */
    private function insertLogEntry(
        LogEntry $entry,
    ): void {
        $record = $this->resolveMapper()->map(
            self::DEFAULT_PROJECT_ID,
            $entry,
        );

        $this->connection->insert(
            self::TABLE_NAME,
            [
                'project_id' => (int) $record->getProjectId(),

                'external_id' => $record->getExternalId(),

                'fingerprint' => $record->getFingerprint(),

                'message' => $record->getMessage(),

                'level' => $record->getLevel(),

                'domain' => $record->getDomain(),

                'env' => $record->getEnv(),

                'http_status' => $record->getHttpStatus(),

                'client' => $record->getClient(),

                'request_id' => $record->getRequestId(),

                'method' => $record->getMethod(),

                'uri' => $record->getUri(),

                'user_agent' => $record->getUserAgent(),

                'ip' => $record->getIp(),

                'context_json' => $this->encodeJson(
                    $record->getContextJson(),
                ),

                'extra_json' => $this->encodeJson(
                    $record->getExtraJson(),
                ),

                'ingestion_warnings_json' => $this->encodeJson(
                    $record->getIngestionWarningsJson(),
                ),

                'created_at' => $record
                    ->getCreatedAt()
                    ->format('Y-m-d H:i:s'),

                'client_date' => $record
                    ->getClientDate()
                    ?->format('Y-m-d H:i:s'),
            ],
        );
    }

    private function resolveMapper(): LogEntryToRecordMapper
    {
        return $this->mapper ?? new LogEntryToRecordMapper();
    }

    /**
     * Limite défensivement la taille
     * du batch de persistence.
     *
     * @param list<LogEntry> $entries
     *
     * @return list<LogEntry>
     */
    private function limitBatchSize(
        array $entries,
    ): array {
        if (
            count($entries)
            <= self::MAX_BATCH_SIZE
        ) {
            return $entries;
        }

        $this->logger->warning(
            'Persistence batch truncated.',
            [
                'original_size' => count($entries),
                'max_size' => self::MAX_BATCH_SIZE,
            ],
        );

        return array_slice(
            $entries,
            0,
            self::MAX_BATCH_SIZE,
        );
    }

    /**
     * Encode un tableau JSON
     * de manière robuste.
     */
    private function encodeJson(
        array $data,
    ): string {
        try {
            $json = json_encode(
                $data,
                JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES,
            );

            if ($json === false) {
                return '{}';
            }

            return $json;
        } catch (\Throwable) {
            return '{}';
        }
    }

    /**
     * Normalise les warnings ingestion.
     *
     * @param list<IngestionWarning> $warnings
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeWarnings(
        array $warnings,
    ): array {
        return array_map(
            static fn(
                IngestionWarning $warning,
            ): array => $warning->toArray(),
            $warnings,
        );
    }

    /**
     * Tronque une string.
     */
    private function truncate(
        string $value,
        int $maxLength,
    ): string {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

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
     * Normalise une string nullable.
     */
    private function normalizeNullableString(
        ?string $value,
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return $this->truncate(
            $value,
            1000,
        );
    }

    /**
     * Construit un message d'erreur borné.
     */
    private function buildErrorMessage(
        LogEntry $entry,
        \Throwable $exception,
    ): string {
        return sprintf(
            '[%s] %s',
            $entry->getExternalId(),
            $exception->getMessage(),
        );
    }

    /**
     * Journalise un échec d'insertion.
     */
    private function logInsertionFailure(
        LogEntry $entry,
        \Throwable $exception,
    ): void {
        $this->logger->error(
            'Log insertion failed.',
            [
                'external_id' => $entry->getExternalId(),

                'fingerprint' => $entry
                    ->getFingerprint()
                    ->value(),

                'domain' => $entry->getDomain(),

                'level' => $entry
                    ->getLevel()
                    ->value,

                'http_status' => $entry
                    ->getHttpStatus()
                    ->value(),

                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ],
        );
    }

    /**
     * Rollback sécurisé.
     */
    private function safeRollback(): void
    {
        try {
            if (
                $this->connection
                ->isTransactionActive()
            ) {
                $this->connection->rollBack();
            }
        } catch (\Throwable $exception) {
            $this->logger->critical(
                'Transaction rollback failure.',
                [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ],
            );
        }
    }
}
