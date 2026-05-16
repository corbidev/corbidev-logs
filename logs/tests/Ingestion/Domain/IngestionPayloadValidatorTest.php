<?php

declare(strict_types=1);

namespace App\Tests\Ingestion\Domain;

use App\Ingestion\Domain\IngestionPayloadValidator;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Tests unitaires du validateur de payload d'ingestion.
 */
final class IngestionPayloadValidatorTest extends TestCase
{
    /**
     * Validateur testé.
     */
    private IngestionPayloadValidator $validator;

    /**
     * But : Initialiser le validateur avant chaque test.
     *
     * Entrée : Démarrage d'un test unitaire.
     * Résultat attendu : Une instance valide du validateur est disponible.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new IngestionPayloadValidator();
    }

    /**
     * But : Vérifier qu'un payload avec logs non vide est accepté.
     *
     * Entrée : ['logs' => [['message' => 'ok']]].
     * Résultat attendu : validate() retourne null.
     */
    public function test_it_accepts_valid_logs_payload(): void
    {
        $error = $this->validator->validate([
            'logs' => [
                [
                    'message' => 'ok',
                ],
            ],
        ]);

        self::assertNull($error);
    }

    /**
     * But : Vérifier qu'un payload sans logs est refusé avec un message stable.
     *
     * Entrée : ['message' => 'missing logs'].
     * Résultat attendu : Message d'erreur sur l'absence de logs.
     */
    public function test_it_rejects_payload_without_logs(): void
    {
        $error = $this->validator->validate([
            'message' => 'missing logs',
        ]);

        self::assertSame(
            'Payload must contain a "logs" field.',
            $error,
        );
    }

    /**
     * But : Vérifier qu'un tableau logs vide est refusé avec un message stable.
     *
     * Entrée : ['logs' => []].
     * Résultat attendu : Message d'erreur sur le lot vide.
     */
    public function test_it_rejects_empty_logs_array(): void
    {
        $error = $this->validator->validate([
            'logs' => [],
        ]);

        self::assertSame(
            'The "logs" field must not be empty.',
            $error,
        );
    }

    /**
     * But : Vérifier qu'une entrée non objet est refusée avec un message stable.
     *
     * Entrée : ['logs' => ['invalid-entry']].
     * Résultat attendu : Message d'erreur avec index incriminé.
     */
    public function test_it_rejects_non_object_log_entries(): void
    {
        $error = $this->validator->validate([
            'logs' => [
                'invalid-entry',
            ],
        ]);

        self::assertSame(
            'Each log entry must be an object. Invalid entry at index 0.',
            $error,
        );
    }
}
