<?php

declare(strict_types=1);

namespace App\Persistence\Application;

use App\Persistence\Domain\LogWriterInterface;
use App\Persistence\Domain\PersistenceResult;

/**
 * Handler applicatif de persistence batch.
 *
 * RESPONSABILITÉ :
 * ----------------
 * Orchestre la persistence durable
 * d'un batch de logs normalisés.
 *
 * OBJECTIFS :
 * -----------
 * - simplicité
 * - robustesse
 * - prédictibilité
 * - orchestration explicite
 * - protection de l'infrastructure
 *
 * IMPORTANT :
 * ------------
 * Ce handler :
 *
 * - ne valide PAS les logs
 * - ne normalise PAS les données
 * - ne contient PAS de SQL
 * - ne dépend PAS de Symfony
 * - ne dépend PAS de Doctrine
 *
 * RESPONSABILITÉS AUTORISÉES :
 * ----------------------------
 * - protéger la couche persistence
 * - éviter les appels inutiles
 * - orchestrer l'écriture
 * - capturer les erreurs inattendues
 *
 * GARANTIES :
 * -----------
 * - aucun crash brutal
 * - aucune exception technique exposée
 * - comportement stable
 * - résultat toujours explicite
 *
 * FLUX :
 * ------
 * PersistLogBatchRequest
 *          ↓
 * PersistLogBatchHandler
 *          ↓
 * LogWriterInterface
 *          ↓
 * Infrastructure SQL
 */
final readonly class PersistLogBatchHandler
{
    public function __construct(
        private LogWriterInterface $writer,
    ) {
    }

    /**
     * Persiste un batch de logs.
     *
     * IMPORTANT :
     * ------------
     * Ce handler ne doit jamais :
     *
     * - provoquer d'erreur fatale
     * - laisser fuiter une exception
     * - appeler inutilement la persistence
     */
    public function handle(
        PersistLogBatchRequest $request,
    ): PersistenceResult {
        if ($request->isEmpty()) {
            return PersistenceResult::nothingToPersist();
        }

        try {
            $result = $this->writer->persist(
                $request->getEntries(),
            );

            return $this->normalizeResult(
                $result,
            );
        } catch (\Throwable $exception) {
            return $this->createFailureResult(
                $request->count(),
                $exception,
            );
        }
    }

    /**
     * Normalise défensivement
     * le résultat retourné.
     *
     * Garanties :
     * - jamais null
     * - jamais incohérent
     * - toujours explicite
     */
    private function normalizeResult(
        PersistenceResult $result,
    ): PersistenceResult {
        return $result;
    }

    /**
     * Crée un résultat d'échec
     * stable et sécurisé.
     *
     * IMPORTANT :
     * ------------
     * Aucune exception technique
     * ne doit fuiter vers l'extérieur.
     */
    private function createFailureResult(
        int $failedCount,
        \Throwable $exception,
    ): PersistenceResult {
        return PersistenceResult::failure(
            failedCount: max(
                1,
                $failedCount,
            ),
            errors: [
                $this->buildSafeErrorMessage(
                    $exception,
                ),
            ],
        );
    }

    /**
     * Construit un message d'erreur
     * borné et sécurisé.
     *
     * IMPORTANT :
     * ------------
     * - jamais vide
     * - jamais multi-lignes
     * - jamais excessivement long
     * - aucune stacktrace
     */
    private function buildSafeErrorMessage(
        \Throwable $exception,
    ): string {
        $message = trim(
            $exception->getMessage(),
        );

        if ($message === '') {
            return 'Unexpected persistence failure.';
        }

        $message = preg_replace(
            '/\s+/u',
            ' ',
            $message,
        );

        if (!is_string($message)) {
            return 'Unexpected persistence failure.';
        }

        $message = mb_substr(
            $message,
            0,
            300,
        );

        return sprintf(
            'Unexpected persistence failure: %s',
            $message,
        );
    }
}