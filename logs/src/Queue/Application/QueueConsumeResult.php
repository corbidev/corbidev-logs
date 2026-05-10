<?php

declare(strict_types=1);

namespace App\Queue\Application;

/**
 * Résultat agrégé d'une consommation de queue.
 *
 * Responsabilités :
 * - stocker les métriques du batch
 * - exposer les statistiques de consommation
 * - fournir un état final cohérent
 *
 * Invariants :
 * - compteurs toujours positifs
 * - aucune valeur négative
 * - aucun état incohérent
 * - aucune logique métier
 * - aucun accès infrastructure
 *
 * Cette classe représente uniquement :
 *
 * QueueConsumer
 * →
 * résultat batch
 *
 * Utilisée par :
 * - commandes Symfony
 * - cron
 * - monitoring
 * - tests
 * - métriques
 *
 * Cette classe doit rester :
 * - simple
 * - immutable côté lecture
 * - prédictible
 * - sans dépendance externe
 */
final class QueueConsumeResult
{
    /**
     * Nombre de payloads persistés avec succès.
     */
    private int $processedCount = 0;

    /**
     * Nombre de payloads échoués.
     */
    private int $failedCount = 0;

    /**
     * Nombre de fichiers déplacés en failed/.
     */
    private int $movedToFailedCount = 0;

    /**
     * Incrémente le nombre de payloads persistés.
     */
    public function incrementProcessed(): void
    {
        ++$this->processedCount;
    }

    /**
     * Incrémente le nombre de payloads échoués.
     */
    public function incrementFailed(): void
    {
        ++$this->failedCount;
    }

    /**
     * Incrémente le nombre de fichiers déplacés
     * vers failed/.
     */
    public function incrementMovedToFailed(): void
    {
        ++$this->movedToFailedCount;
    }

    /**
     * Retourne le nombre de payloads persistés.
     */
    public function getProcessedCount(): int
    {
        return $this->processedCount;
    }

    /**
     * Retourne le nombre de payloads échoués.
     */
    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    /**
     * Retourne le nombre de fichiers déplacés
     * vers failed/.
     */
    public function getMovedToFailedCount(): int
    {
        return $this->movedToFailedCount;
    }

    /**
     * Retourne le nombre total de payloads traités.
     *
     * Total :
     * processed + failed
     */
    public function getTotalCount(): int
    {
        return $this->processedCount
            + $this->failedCount;
    }

    /**
     * Vérifie si des payloads ont été persistés.
     */
    public function hasProcessedItems(): bool
    {
        return $this->processedCount > 0;
    }

    /**
     * Vérifie si des erreurs existent.
     */
    public function hasFailures(): bool
    {
        return $this->failedCount > 0;
    }

    /**
     * Vérifie si des fichiers ont été déplacés
     * vers failed/.
     */
    public function hasMovedToFailed(): bool
    {
        return $this->movedToFailedCount > 0;
    }

    /**
     * Vérifie si le batch est vide.
     */
    public function isEmpty(): bool
    {
        return 0 === $this->getTotalCount();
    }

    /**
     * Vérifie si tous les payloads ont réussi.
     */
    public function isSuccessful(): bool
    {
        return 0 === $this->failedCount;
    }

    /**
     * Retourne les métriques sous forme de tableau.
     *
     * @return array{
     *     processed: int,
     *     failed: int,
     *     movedToFailed: int,
     *     total: int,
     *     successful: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'processed' => $this->processedCount,
            'failed' => $this->failedCount,
            'movedToFailed' => $this->movedToFailedCount,
            'total' => $this->getTotalCount(),
            'successful' => $this->isSuccessful(),
        ];
    }
}