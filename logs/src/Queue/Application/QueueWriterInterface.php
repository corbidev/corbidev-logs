<?php

declare(strict_types=1);

namespace App\Queue\Application;

/**
 * Contrat d'écriture dans la queue disque.
 *
 * Responsabilités :
 * - sérialiser un log normalisé
 * - écrire un fichier queue atomique
 * - garantir qu'un log valide est sécurisé sur disque
 *
 * Invariants :
 * - 1 log = 1 fichier
 * - aucune modification métier des données
 * - écriture atomique obligatoire
 * - JSON UTF-8 valide uniquement
 *
 * Le writer représente le point de sécurisation
 * du WRITE SIDE avant persistence DB.
 */
interface QueueWriterInterface
{
    /**
     * Écrit un log normalisé dans la queue disque.
     *
     * Le payload fourni DOIT déjà être :
     * - validé
     * - normalisé
     * - sécurisé
     * - UTF-8 valide
     *
     * Cette méthode ne doit jamais :
     * - recalculer les données métier
     * - modifier le payload
     * - enrichir les données
     * - dépendre de la base de données
     *
     * L'implémentation doit garantir :
     * - écriture atomique
     * - absence de fichier partiel
     * - JSON valide
     * - isolation des erreurs disque
     *
     * @param array<string, mixed> $payload
     * Payload de log déjà normalisé.
     *
     * @return string
     * Chemin absolu du fichier queue créé.
     *
     * @throws \JsonException
     * Si la sérialisation JSON échoue.
     *
     * @throws \RuntimeException
     * Si l'écriture disque échoue.
     */
    public function write(array $payload): string;
}