<?php

declare(strict_types=1);

namespace App\Persistence\Domain;

use App\Log\Domain\Entity\LogEntry;

/**
 * Contrat d'écriture durable des logs.
 *
 * RESPONSABILITÉ :
 * ----------------
 * Cette interface définit le point d'entrée unique
 * de persistance durable des LogEntry normalisés.
 *
 * OBJECTIFS :
 * -----------
 * - écriture rapide
 * - comportement prédictible
 * - robustesse maximale
 * - aucune dépendance technique imposée
 * - abstraction minimale
 *
 * GARANTIES :
 * -----------
 * - les LogEntry reçus sont déjà valides
 * - les LogEntry sont immutables
 * - aucune normalisation ici
 * - aucune logique métier ici
 * - aucune lecture ici
 *
 * IMPORTANT :
 * ------------
 * L'implémentation ne doit jamais :
 *
 * - modifier les LogEntry
 * - relancer des exceptions techniques brutes
 * - effectuer de SELECT massif
 * - dépendre du frontend
 * - contenir de logique Symfony
 *
 * RÈGLE CRITIQUE :
 * ----------------
 * Une erreur de persistence ne doit jamais :
 *
 * - casser le processus complet
 * - faire perdre tout le batch
 * - provoquer un état incohérent
 *
 * Les implémentations doivent :
 *
 * - isoler les erreurs
 * - retourner un résultat explicite
 * - rester idempotentes autant que possible
 * - privilégier les écritures simples
 *
 * EXEMPLES D'IMPLÉMENTATION :
 * ---------------------------
 * - MySQLLogWriter
 * - MariaDbLogWriter
 * - NullLogWriter
 * - BufferedLogWriter
 */
interface LogWriterInterface
{
    /**
     * Persiste un batch de logs normalisés.
     *
     * OBJECTIFS :
     * -----------
     * - minimiser les allocations
     * - limiter les requêtes SQL
     * - écrire rapidement
     * - isoler les erreurs
     *
     * CONTRAT :
     * ---------
     * - le tableau peut être vide
     * - chaque entrée DOIT être un LogEntry valide
     * - l'ordre des logs DOIT être conservé
     * - aucune exception ne doit fuiter
     *
     * COMPORTEMENT ATTENDU :
     * ----------------------
     * En cas d'erreur :
     *
     * - capturer les exceptions techniques
     * - retourner un PersistenceResult explicite
     * - ne jamais interrompre brutalement le processus
     *
     * @param list<LogEntry> $entries Batch de logs validés.
     */
    public function persist(array $entries): PersistenceResult;
}