<?php

declare(strict_types=1);

namespace App\Queue\Application;

/**
 * Contrat du consumer de queue.
 *
 * Responsabilités :
 * - orchestrer la consommation de la queue
 * - coordonner lecture et persistence
 * - supprimer les fichiers persistés
 * - déplacer les fichiers en failed
 * - garantir un traitement batch borné
 *
 * Invariants :
 * - aucune logique métier
 * - aucune logique DB spécifique
 * - aucune dépendance Symfony obligatoire
 * - aucun daemon permanent
 *
 * Le consumer orchestre uniquement :
 *
 * QueueReader
 * →
 * QueuePersistence
 * →
 * delete|failed
 *
 * Le consumer doit être :
 * - idempotent
 * - robuste aux erreurs
 * - mémoire bornée
 * - compatible CRON
 *
 * Le consumer ne doit jamais :
 * - charger toute la queue en mémoire
 * - arrêter le batch sur une erreur isolée
 * - masquer les erreurs techniques
 * - dépendre directement de Doctrine
 */
interface QueueConsumerInterface
{
    /**
     * Consomme un batch de queue.
     *
     * Le traitement doit :
     * - être borné
     * - être progressif
     * - être robuste aux erreurs isolées
     *
     * Le consumer doit continuer
     * même si certains payloads échouent.
     *
     * @param positive-int $limit
     * Nombre maximum de fichiers à traiter.
     *
     * @return QueueConsumeResult
     * Résultat agrégé du batch.
     *
     * @throws \Throwable
     * Seulement pour les erreurs globales critiques :
     * - queue inaccessible
     * - disque indisponible
     * - configuration invalide
     */
    public function consume(
        int $limit = 100,
    ): QueueConsumeResult;
}