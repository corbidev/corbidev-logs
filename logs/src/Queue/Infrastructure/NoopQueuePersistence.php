<?php

declare(strict_types=1);

namespace App\Queue\Infrastructure;

use App\Queue\Application\QueuePersistenceInterface;

/**
 * Persistence no-op pour amorcer la commande queue.
 *
 * Responsabilités :
 * - fournir une implémentation stable de QueuePersistenceInterface
 * - permettre un traitement batch sans erreur fatale
 * - préparer le câblage vers la vraie persistence (Q-002)
 */
final class NoopQueuePersistence implements QueuePersistenceInterface
{
    /**
     * {@inheritDoc}
     */
    public function persist(array $payload): void
    {
        // Intentionnellement vide : la vraie persistence est branchée en Q-002.
    }
}
