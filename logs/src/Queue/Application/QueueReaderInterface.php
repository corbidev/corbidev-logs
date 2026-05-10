<?php

declare(strict_types=1);

namespace App\Queue\Application;

/**
 * Contrat de lecture de la queue disque.
 *
 * Responsabilités :
 * - lire les fichiers queue par batch
 * - garantir une consommation mémoire bornée
 * - fournir un flux de lecture prédictible
 * - isoler les corruptions
 *
 * Invariants :
 * - 1 fichier = 1 log
 * - lecture progressive uniquement
 * - aucun chargement massif mémoire
 * - aucune logique métier
 *
 * Le reader représente le point d'entrée
 * du consumer CRON avant persistence DB.
 */
interface QueueReaderInterface
{
    /**
     * Lit un batch limité de fichiers queue.
     *
     * L'implémentation doit :
     * - limiter strictement le nombre de fichiers lus
     * - retourner un flux ordonné de manière stable
     * - ignorer les fichiers non exploitables
     * - isoler les fichiers corrompus
     * - éviter toute surcharge mémoire
     *
     * L'ordre recommandé est :
     * FIFO approximatif basé sur le nom de fichier.
     *
     * Chaque élément retourné doit contenir :
     * - le chemin du fichier source
     * - le payload JSON décodé
     *
     * Exemple :
     *
     * [
     *     [
     *         'path' => '/var/queue/logs/20260510_021522_ab12cd34.json',
     *         'payload' => [
     *             'message' => 'Erreur SQL',
     *             'level' => 'error',
     *         ],
     *     ],
     * ]
     *
     * @param positive-int $limit
     * Nombre maximum de fichiers à lire.
     *
     * @return iterable<int, array{
     *     path: string,
     *     payload: array<string, mixed>
     * }>
     *
     * @throws \RuntimeException
     * Si la lecture disque échoue.
     */
    public function readBatch(int $limit = 100): iterable;

    /**
     * Supprime un fichier queue après persistence réussie.
     *
     * Cette méthode ne doit être appelée
     * qu'après confirmation explicite
     * de persistence en base de données.
     *
     * L'implémentation doit :
     * - vérifier l'existence du fichier
     * - garantir une suppression sûre
     * - lever une exception explicite en cas d'échec
     *
     * @param string $path
     * Chemin absolu du fichier queue.
     *
     * @throws \RuntimeException
     * Si la suppression échoue.
     */
    public function delete(string $path): void;

    /**
     * Déplace un fichier queue vers la zone "processing".
     *
     * Permet d'éviter qu'un second consumer
     * traite simultanément le même fichier.
     *
     * L'opération doit être atomique.
     *
     * @param string $path
     * Chemin absolu du fichier queue.
     *
     * @return string
     * Nouveau chemin du fichier déplacé.
     *
     * @throws \RuntimeException
     * Si le déplacement échoue.
     */
    public function markAsProcessing(string $path): string;

    /**
     * Déplace un fichier queue vers la zone "failed".
     *
     * Utilisé lorsque le nombre maximal
     * de retries est dépassé.
     *
     * Le fichier ne doit jamais être supprimé directement.
     *
     * @param string $path
     * Chemin absolu du fichier queue.
     *
     * @return string
     * Nouveau chemin du fichier déplacé.
     *
     * @throws \RuntimeException
     * Si le déplacement échoue.
     */
    public function moveToFailed(string $path): string;
}