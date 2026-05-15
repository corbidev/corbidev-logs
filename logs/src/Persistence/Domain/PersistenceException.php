<?php

declare(strict_types=1);

namespace App\Persistence\Domain;

use App\Persistence\Constantes\PersistenceLimits;
use App\Persistence\Enum\PersistenceErrorCode;
use Throwable;

/**
 * Exception métier de persistence.
 *
 * Responsabilités :
 * - encapsuler les erreurs de persistence
 * - fournir un code métier stable
 * - exposer un contexte borné et sécurisé
 * - garantir des messages prédictibles
 * - protéger contre les payloads hostiles
 *
 * Invariants :
 * - immutable
 * - aucun payload massif
 * - aucun secret exposé
 * - aucun contexte non borné
 * - aucun objet complexe dans le contexte
 * - aucun tableau imbriqué
 */
final class PersistenceException extends \RuntimeException
{
    /**
     * Contexte technique sécurisé.
     *
     * IMPORTANT :
     * - uniquement scalar|null
     * - borné
     * - nettoyé
     * - immutable
     *
     * @var array<string, scalar|null>
     */
    private readonly array $context;

    /**
     * Code métier stable.
     */
    private readonly PersistenceErrorCode $errorCode;

    /**
     * @param array<string, scalar|null> $context
     */
    private function __construct(
        PersistenceErrorCode $errorCode,
        string $message,
        array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: self::sanitizeMessage($message),
            code: $errorCode->value,
            previous: $previous,
        );

        $this->errorCode = $errorCode;
        $this->context = $this->sanitizeContext($context);
    }

    /**
     * Erreur de connexion DB.
     *
     * @param array<string, scalar|null> $context
     */
    public static function databaseConnectionFailed(
        array $context = [],
        ?Throwable $previous = null,
    ): self {
        return new self(
            errorCode: PersistenceErrorCode::DATABASE_CONNECTION_FAILED,
            message: 'Database connection failed.',
            context: $context,
            previous: $previous,
        );
    }

    /**
     * Erreur SQL.
     *
     * @param array<string, scalar|null> $context
     */
    public static function queryExecutionFailed(
        array $context = [],
        ?Throwable $previous = null,
    ): self {
        return new self(
            errorCode: PersistenceErrorCode::QUERY_EXECUTION_FAILED,
            message: 'SQL query execution failed.',
            context: $context,
            previous: $previous,
        );
    }

    /**
     * Batch vide.
     *
     * @param array<string, scalar|null> $context
     */
    public static function emptyBatch(
        array $context = [],
    ): self {
        return new self(
            errorCode: PersistenceErrorCode::EMPTY_BATCH,
            message: 'Persistence batch is empty.',
            context: $context,
        );
    }

    /**
     * Batch trop volumineux.
     *
     * @param array<string, scalar|null> $context
     */
    public static function batchTooLarge(
        array $context = [],
    ): self {
        return new self(
            errorCode: PersistenceErrorCode::BATCH_TOO_LARGE,
            message: 'Persistence batch exceeds allowed limit.',
            context: $context,
        );
    }

    /**
     * Payload invalide.
     *
     * @param array<string, scalar|null> $context
     */
    public static function invalidPayload(
        array $context = [],
        ?Throwable $previous = null,
    ): self {
        return new self(
            errorCode: PersistenceErrorCode::INVALID_PAYLOAD,
            message: 'Persistence payload is invalid.',
            context: $context,
            previous: $previous,
        );
    }

    /**
     * Timeout DB.
     *
     * @param array<string, scalar|null> $context
     */
    public static function timeout(
        array $context = [],
        ?Throwable $previous = null,
    ): self {
        return new self(
            errorCode: PersistenceErrorCode::TIMEOUT,
            message: 'Persistence operation timed out.',
            context: $context,
            previous: $previous,
        );
    }

    /**
     * Deadlock SQL.
     *
     * @param array<string, scalar|null> $context
     */
    public static function deadlock(
        array $context = [],
        ?Throwable $previous = null,
    ): self {
        return new self(
            errorCode: PersistenceErrorCode::DEADLOCK,
            message: 'Database deadlock detected.',
            context: $context,
            previous: $previous,
        );
    }

    /**
     * Echec transaction.
     *
     * @param array<string, scalar|null> $context
     */
    public static function transactionFailed(
        array $context = [],
        ?Throwable $previous = null,
    ): self {
        return new self(
            errorCode: PersistenceErrorCode::TRANSACTION_FAILED,
            message: 'Database transaction failed.',
            context: $context,
            previous: $previous,
        );
    }

    /**
     * Retourne le code métier.
     */
    public function getErrorCode(): PersistenceErrorCode
    {
        return $this->errorCode;
    }

    /**
     * Retourne le contexte sécurisé.
     *
     * @return array<string, scalar|null>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Sécurise le message d'erreur.
     */
    private static function sanitizeMessage(string $message): string
    {
        $message = trim($message);

        if ($message === '') {
            return 'Persistence error.';
        }

        return mb_substr(
            string: $message,
            start: 0,
            length: PersistenceLimits::MAX_ERROR_LENGTH,
        );
    }

    /**
     * Normalise le contexte d'erreur.
     *
     * IMPORTANT :
     * - structure bornée
     * - clés nettoyées
     * - strings limitées
     * - objets ignorés
     * - tableaux ignorés
     *
     * @param array<string, scalar|null> $context
     *
     * @return array<string, scalar|null>
     */
    private function sanitizeContext(array $context): array
    {
        $sanitized = [];

        $count = 0;

        foreach ($context as $key => $value) {
            if ($count >= PersistenceLimits::MAX_EXCEPTION_CONTEXT_ITEMS) {
                break;
            }

            $normalizedKey = trim((string) $key);

            if ($normalizedKey === '') {
                continue;
            }

            $normalizedKey = mb_substr(
                string: $normalizedKey,
                start: 0,
                length: PersistenceLimits::MAX_EXCEPTION_CONTEXT_KEY_LENGTH,
            );

            if (is_array($value)) {
                continue;
            }

            if (is_object($value)) {
                continue;
            }

            if (is_resource($value)) {
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);

                $value = mb_substr(
                    string: $value,
                    start: 0,
                    length: PersistenceLimits::MAX_EXCEPTION_CONTEXT_VALUE_LENGTH,
                );
            }

            $sanitized[$normalizedKey] = $value;

            ++$count;
        }

        return $sanitized;
    }
}