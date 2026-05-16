<?php

declare(strict_types=1);

namespace App\Ingestion\Domain;

/**
 * Valide la structure minimale d'un payload d'ingestion.
 *
 * Responsabilités :
 * - vérifier la présence de logs
 * - garantir que logs est un tableau
 * - refuser un lot vide
 * - vérifier que chaque entrée est un objet JSON décodé
 */
final class IngestionPayloadValidator
{
    /**
     * Valide le payload racine et retourne un message stable si invalide.
     */
    public function validate(
        mixed $payload,
    ): ?string {
        if (!is_array($payload)) {
            return 'Payload must be a JSON object.';
        }

        if (!array_key_exists('logs', $payload)) {
            return 'Payload must contain a "logs" field.';
        }

        if (!is_array($payload['logs'])) {
            return 'The "logs" field must be an array.';
        }

        if ($payload['logs'] === []) {
            return 'The "logs" field must not be empty.';
        }

        foreach ($payload['logs'] as $index => $log) {
            if (!is_array($log)) {
                return sprintf(
                    'Each log entry must be an object. Invalid entry at index %d.',
                    $index,
                );
            }
        }

        return null;
    }
}
