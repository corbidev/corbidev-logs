<?php

declare(strict_types=1);

namespace App\Queue\Application;

/**
 * Contrat de persistence des payloads issus de la queue.
 *
 * Responsabilités :
 * - persister un payload queue valide
 * - encapsuler la logique technique de persistence
 * - isoler Queue du moteur de persistence
 *
 * Invariants :
 * - aucune logique queue
 * - aucune dépendance filesystem
 * - aucune logique retry
 * - aucune suppression de fichier
 *
 * Cette interface représente la frontière entre :
 *
 * Queue
 * →
 * Persistence
 *
 * Le payload fourni :
 * - provient du QueueReader
 * - est déjà décodé JSON
 * - reste considéré hostile
 *
 * La persistence DOIT :
 * - revalider les données nécessaires
 * - lever une exception explicite si échec
 *
 * Le consumer décidera ensuite :
 * - delete()
 * - moveToFailed()
 *
 * Cette interface permet :
 * - Doctrine
 * - PDO
 * - fichier
 * - HTTP
 * - tests mémoire
 *
 * sans couplage avec la queue.
 */
interface QueuePersistenceInterface
{
    /**
     * Persiste un payload queue.
     *
     * Le payload :
     * - provient directement de la queue disque
     * - ne doit jamais être considéré fiable
     * - peut être invalide ou incomplet
     *
     * En cas d'échec :
     * - lever une exception explicite
     * - ne jamais retourner false
     * - ne jamais masquer une erreur technique
     *
     * @param array<string, mixed> $payload
     *
     * @throws \Throwable
     * Toute erreur de persistence.
     */
    public function persist(
        array $payload,
    ): void;
}