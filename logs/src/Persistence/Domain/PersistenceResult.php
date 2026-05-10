<?php

declare(strict_types=1);

namespace App\Persistence\Domain;

use App\Persistence\Constantes\PersistenceLimits;

/**
 * Représente le résultat explicite
 * d'une opération de persistence.
 *
 * Objectifs :
 * - immutable
 * - prédictible
 * - robuste
 * - sans ambiguïté
 * - résistant aux payloads hostiles
 *
 * Invariants :
 * - compteurs toujours valides
 * - erreurs normalisées
 * - structure bornée
 * - aucune donnée non scalaire
 */
final readonly class PersistenceResult
{
    /**
     * Liste bornée des erreurs.
     *
     * IMPORTANT :
     * - unique
     * - nettoyée
     * - immutable
     * - sans doublons
     *
     * @var list<string>
     */
    private array $errors;

    /**
     * @param list<mixed> $errors
     */
    public function __construct(
        private int $persistedCount,
        private int $failedCount,
        array $errors = [],
    ) {
        $this->assertCountsAreValid(
            persistedCount: $persistedCount,
            failedCount: $failedCount,
        );

        $this->errors = $this->normalizeErrors($errors);
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
        );
    }

    /**
     * Crée un résultat totalement échoué.
     *
     * @param list<mixed> $errors
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
     * Crée un résultat partiellement réussi.
     *
     * @param list<mixed> $errors
     */
    public static function partial(
        int $persistedCount,
        int $failedCount,
        array $errors = [],
    ): self {
        return new self(
            persistedCount: $persistedCount,
            failedCount: $failedCount,
            errors: $errors,
        );
    }

    /**
     * Nombre de logs persistés.
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
        return $this->safeAdd(
            $this->persistedCount,
            $this->failedCount,
        );
    }

    /**
     * Indique si au moins un log
     * a été persisté.
     */
    public function hasPersistedLogs(): bool
    {
        return $this->persistedCount > 0;
    }

    /**
     * Indique si au moins un log
     * a échoué.
     */
    public function hasFailures(): bool
    {
        return $this->failedCount > 0;
    }

    /**
     * Indique si des erreurs existent.
     */
    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * Indique si la persistence
     * est totalement réussie.
     */
    public function isSuccess(): bool
    {
        return $this->persistedCount > 0
            && $this->failedCount === 0;
    }

    /**
     * Indique si la persistence
     * est totalement échouée.
     */
    public function isFailure(): bool
    {
        return $this->persistedCount === 0
            && $this->failedCount > 0;
    }

    /**
     * Indique si le résultat
     * est partiel.
     */
    public function isPartial(): bool
    {
        return $this->persistedCount > 0
            && $this->failedCount > 0;
    }

    /**
     * Retourne le ratio de réussite.
     */
    public function getSuccessRate(): float
    {
        $total = $this->getTotalCount();

        if ($total <= 0) {
            return 0.0;
        }

        $rate = ($this->persistedCount / $total) * 100;

        if (
            is_nan($rate)
            || is_infinite($rate)
        ) {
            return 0.0;
        }

        return round(
            num: max(
                0.0,
                min(100.0, $rate),
            ),
            precision: 2,
        );
    }

    /**
     * Fusionne deux résultats.
     */
    public function merge(
        self $other,
    ): self {
        return new self(
            persistedCount: $this->safeAdd(
                $this->persistedCount,
                $other->persistedCount,
            ),
            failedCount: $this->safeAdd(
                $this->failedCount,
                $other->failedCount,
            ),
            errors: [
                ...$this->errors,
                ...$other->errors,
            ],
        );
    }

    /**
     * Export tableau sécurisé.
     *
     * @return array{
     *     persistedCount:int,
     *     failedCount:int,
     *     totalCount:int,
     *     successRate:float,
     *     isSuccess:bool,
     *     isFailure:bool,
     *     isPartial:bool,
     *     errors:list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'persistedCount' => $this->persistedCount,
            'failedCount' => $this->failedCount,
            'totalCount' => $this->getTotalCount(),
            'successRate' => $this->getSuccessRate(),
            'isSuccess' => $this->isSuccess(),
            'isFailure' => $this->isFailure(),
            'isPartial' => $this->isPartial(),
            'errors' => $this->errors,
        ];
    }

    /**
     * Validation défensive des compteurs.
     */
    private function assertCountsAreValid(
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
     * Addition défensive protégée
     * contre overflow.
     */
    private function safeAdd(
        int $left,
        int $right,
    ): int {
        if (
            $right > 0
            && $left > PHP_INT_MAX - $right
        ) {
            return PHP_INT_MAX;
        }

        return $left + $right;
    }

    /**
     * Normalise les erreurs.
     *
     * IMPORTANT :
     * - supprime objets
     * - supprime tableaux
     * - limite longueur
     * - retire doublons
     * - borne volume
     *
     * @param list<mixed> $errors
     *
     * @return list<string>
     */
    private function normalizeErrors(
        array $errors,
    ): array {
        $normalized = [];

        foreach ($errors as $error) {
            if (
                !is_scalar($error)
                && !$error instanceof \Stringable
            ) {
                continue;
            }

            $message = trim(
                (string) $error,
            );

            if ($message === '') {
                continue;
            }

            $message = mb_substr(
                string: $message,
                start: 0,
                length: PersistenceLimits::MAX_ERROR_LENGTH,
            );

            $normalized[] = $message;
        }

        return array_slice(
            array: array_values(
                array_unique($normalized),
            ),
            offset: 0,
            length: PersistenceLimits::MAX_ERRORS,
        );
    }
}