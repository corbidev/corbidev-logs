<?php

declare(strict_types=1);

namespace App\Persistence\Application;

/**
 * Résultat explicite d'une persistence batch.
 *
 * RESPONSABILITÉS :
 * -----------------
 * - exposer un résultat immutable
 * - fournir des compteurs fiables
 * - encapsuler les erreurs techniques
 * - rester prédictible
 * - éviter tout booléen ambigu
 *
 * INVARIANTS :
 * ------------
 * - immutable
 * - compteurs >= 0
 * - erreurs normalisées
 * - aucune valeur incohérente
 *
 * IMPORTANT :
 * ------------
 * Cet objet ne doit JAMAIS :
 * - lancer d'exception
 * - contenir de logique SQL
 * - dépendre de Doctrine
 * - dépendre de Symfony
 * - contenir de logique métier complexe
 */
final readonly class PersistLogBatchResult
{
    /**
     * @var list<string>
     */
    private array $errors;

    /**
     * @param list<string> $errors
     */
    public function __construct(
        private int $persistedCount,
        private int $failedCount,
        array $errors = [],
    ) {
        $this->guardCounts(
            persistedCount: $persistedCount,
            failedCount: $failedCount,
        );

        $this->errors = $this->normalizeErrors($errors);
    }

    /**
     * Crée un résultat vide.
     */
    public static function empty(): self
    {
        return new self(
            persistedCount: 0,
            failedCount: 0,
            errors: [],
        );
    }

    /**
     * Crée un résultat totalement réussi.
     */
    public static function success(
        int $persistedCount,
    ): self {
        return new self(
            persistedCount: $persistedCount,
            failedCount: 0,
            errors: [],
        );
    }

    /**
     * Crée un résultat totalement échoué.
     *
     * @param list<string> $errors
     */
    public static function failure(
        int $failedCount,
        array $errors = [],
    ): self {
        return new self(
            persistedCount: 0,
            failedCount: $failedCount,
            errors: $errors,
        );
    }

    /**
     * Nombre de logs persistés avec succès.
     */
    public function getPersistedCount(): int
    {
        return $this->persistedCount;
    }

    /**
     * Nombre de logs échoués.
     */
    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    /**
     * Retourne les erreurs normalisées.
     *
     * @return list<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Nombre total de logs traités.
     */
    public function getTotalCount(): int
    {
        return $this->persistedCount + $this->failedCount;
    }

    /**
     * Indique si au moins un log a été persisté.
     */
    public function hasPersistedLogs(): bool
    {
        return $this->persistedCount > 0;
    }

    /**
     * Indique si au moins un log a échoué.
     */
    public function hasFailures(): bool
    {
        return $this->failedCount > 0;
    }

    /**
     * Indique si aucune erreur n'est présente.
     */
    public function hasNoFailures(): bool
    {
        return $this->failedCount === 0;
    }

    /**
     * Retourne une représentation tableau stable.
     *
     * @return array{
     *     persisted_count:int,
     *     failed_count:int,
     *     total_count:int,
     *     errors:list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'persisted_count' => $this->persistedCount,
            'failed_count' => $this->failedCount,
            'total_count' => $this->getTotalCount(),
            'errors' => $this->errors,
        ];
    }

    /**
     * Fusionne plusieurs résultats batch.
     *
     * IMPORTANT :
     * ------------
     * Utilisé pour :
     * - retry partiels
     * - sous-batches
     * - persistence dégradée
     *
     * @param iterable<self> $results
     */
    public static function merge(
        iterable $results,
    ): self {
        $persistedCount = 0;
        $failedCount = 0;

        /** @var list<string> $errors */
        $errors = [];

        foreach ($results as $result) {
            $persistedCount += $result->persistedCount;
            $failedCount += $result->failedCount;

            foreach ($result->errors as $error) {
                $errors[] = $error;
            }
        }

        return new self(
            persistedCount: $persistedCount,
            failedCount: $failedCount,
            errors: $errors,
        );
    }

    /**
     * Vérifie les invariants numériques.
     */
    private function guardCounts(
        int $persistedCount,
        int $failedCount,
    ): void {
        if ($persistedCount < 0) {
            throw new \InvalidArgumentException(
                'Persisted count cannot be negative.',
            );
        }

        if ($failedCount < 0) {
            throw new \InvalidArgumentException(
                'Failed count cannot be negative.',
            );
        }
    }

    /**
     * Normalise les erreurs.
     *
     * OBJECTIFS :
     * -----------
     * - stabilité
     * - protection mémoire
     * - UTF-8 sûr
     * - logs exploitables
     *
     * @param array<mixed> $errors
     *
     * @return list<string>
     */
    private function normalizeErrors(
        array $errors,
    ): array {
        $normalized = [];

        foreach ($errors as $error) {
            if (!is_scalar($error) && !$error instanceof \Stringable) {
                continue;
            }

            $message = trim((string) $error);

            if ($message === '') {
                continue;
            }

            if (!mb_check_encoding($message, 'UTF-8')) {
                $message = mb_convert_encoding(
                    $message,
                    'UTF-8',
                    'UTF-8',
                );
            }

            $message = mb_substr($message, 0, 1000);

            $normalized[] = $message;
        }

        return array_values(array_unique($normalized));
    }
}