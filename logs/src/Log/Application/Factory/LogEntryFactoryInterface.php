<?php

declare(strict_types=1);

namespace App\Log\Application\Factory;

use App\Log\Domain\Entity\LogEntry;

/**
 * Factory responsable de la création des LogEntry.
 *
 * Responsabilités :
 * - centraliser la création des LogEntry
 * - encapsuler la normalisation finale
 * - protéger le domaine des payloads externes
 * - garantir une création stable et prédictible
 */
interface LogEntryFactoryInterface
{
    /**
     * Crée un LogEntry depuis un payload normalisé.
     *
     * @param array<string, mixed> $payload
     */
    public function create(
        array $payload,
    ): LogEntry;
}