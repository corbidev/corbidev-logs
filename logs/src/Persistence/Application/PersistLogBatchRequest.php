<?php

declare(strict_types=1);

namespace App\Persistence\Application;

use App\Log\Domain\Entity\LogEntry;

/**
 * Requête immutable de persistence batch.
 *
 * RESPONSABILITÉ :
 * ----------------
 * Transporte un batch de LogEntry validés
 * vers la couche de persistence.
 *
 * OBJECTIFS :
 * -----------
 * - simplicité maximale
 * - robustesse
 * - allocations minimales
 * - comportement prédictible
 * - immutabilité stricte
 *
 * IMPORTANT :
 * ------------
 * Cette classe :
 *
 * - ne valide PAS les logs
 * - ne normalise PAS les données
 * - ne persiste PAS les données
 * - ne dépend PAS de Symfony
 * - ne contient PAS de logique métier
 *
 * Les LogEntry doivent déjà être :
 *
 * - valides
 * - normalisés
 * - sécurisés
 * - immutables
 *
 * GARANTIES :
 * -----------
 * - ordre conservé
 * - tableau réindexé
 * - données figées
 * - contenu homogène
 *
 * RÈGLES :
 * --------
 * Les valeurs invalides sont ignorées silencieusement
 * afin de protéger la couche applicative.
 *
 * Cette classe doit rester :
 *
 * - petite
 * - explicite
 * - prévisible
 * - sans effet de bord
 */
final readonly class PersistLogBatchRequest
{
    /**
     * @var list<LogEntry>
     */
    private array $entries;

    /**
     * @param iterable<mixed> $entries
     */
    public function __construct(iterable $entries)
    {
        $this->entries = $this->sanitizeEntries($entries);
    }

    /**
     * Retourne les logs du batch.
     *
     * @return list<LogEntry>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    /**
     * Retourne le nombre de logs.
     */
    public function count(): int
    {
        return \count($this->entries);
    }

    /**
     * Indique si le batch est vide.
     */
    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    /**
     * Retourne le premier log du batch.
     */
    public function first(): ?LogEntry
    {
        return $this->entries[0] ?? null;
    }

    /**
     * Retourne le dernier log du batch.
     */
    public function last(): ?LogEntry
    {
        if ($this->entries === []) {
            return null;
        }

        return $this->entries[\array_key_last($this->entries)];
    }

    /**
     * Filtre et réindexe les entrées.
     *
     * OBJECTIFS :
     * -----------
     * - garantir list<LogEntry>
     * - supprimer les données invalides
     * - protéger la couche de persistence
     * - éviter les erreurs runtime
     *
     * IMPORTANT :
     * ------------
     * Aucun cast implicite.
     *
     * @param iterable<mixed> $entries
     *
     * @return list<LogEntry>
     */
    private function sanitizeEntries(iterable $entries): array
    {
        $sanitized = [];

        foreach ($entries as $entry) {
            if (!$entry instanceof LogEntry) {
                continue;
            }

            $sanitized[] = $entry;
        }

        return $sanitized;
    }
}