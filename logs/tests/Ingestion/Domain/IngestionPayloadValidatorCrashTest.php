<?php

declare(strict_types=1);

namespace App\Tests\Ingestion\Domain;

use App\Ingestion\Domain\IngestionPayloadValidator;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Crash tests du validateur de payload d'ingestion.
 *
 * Objectifs :
 * - garantir l'absence d'exception sur entrées hostiles
 * - valider la stabilité des messages d'erreur
 * - vérifier la robustesse sur gros volumes
 */
final class IngestionPayloadValidatorCrashTest extends TestCase
{
    /**
     * Validateur testé.
     */
    private IngestionPayloadValidator $validator;

    /**
     * But : Initialiser le validateur avant chaque test.
     *
     * Entrée : Démarrage d'un test de robustesse.
     * Résultat attendu : Une instance valide du validateur est disponible.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new IngestionPayloadValidator();
    }

    /**
     * But : Vérifier qu'aucune entrée hostile ne provoque d'exception.
     *
     * Entrée : Valeurs hostiles variées (null, scalars, objets, tableaux incohérents, UTF-8, etc.).
     * Résultat attendu : validate() ne throw jamais et retourne null|string.
     */
    public function test_it_never_throws_with_hostile_payloads(): void
    {
        $resource = fopen('php://memory', 'r');

        self::assertIsResource($resource);

        $payloads = [
            null,
            true,
            false,
            '',
            'not-json-object',
            0,
            1,
            -1,
            INF,
            -INF,
            NAN,
            new \stdClass(),
            $resource,
            ['message' => 'missing logs'],
            ['logs' => null],
            ['logs' => true],
            ['logs' => false],
            ['logs' => 'abc'],
            ['logs' => 123],
            ['logs' => []],
            ['logs' => ['scalar-entry']],
            ['logs' => [new \stdClass()]],
            ['logs' => [[
                'message' => "\xB1\x31",
            ]]],
            ['logs' => [[
                'message' => str_repeat('X', 500000),
            ]]],
            ['logs' => [[
                'context' => array_fill(0, 20000, 'v'),
            ]]],
        ];

        foreach ($payloads as $payload) {
            $error = $this->validator->validate($payload);

            self::assertTrue(
                $error === null || is_string($error),
            );
        }

        if (is_resource($resource)) {
            fclose($resource);
        }
    }

    /**
     * But : Vérifier la stabilité des messages pour les erreurs structurelles principales.
     *
     * Entrée : Trois payloads invalides ciblés (sans logs, logs non-array, logs vide).
     * Résultat attendu : Messages exactement identiques aux messages contractuels.
     */
    public function test_it_returns_stable_messages_for_structural_errors(): void
    {
        self::assertSame(
            'Payload must contain a "logs" field.',
            $this->validator->validate([
                'message' => 'missing logs',
            ]),
        );

        self::assertSame(
            'The "logs" field must be an array.',
            $this->validator->validate([
                'logs' => 'not-an-array',
            ]),
        );

        self::assertSame(
            'The "logs" field must not be empty.',
            $this->validator->validate([
                'logs' => [],
            ]),
        );
    }

    /**
     * But : Vérifier la robustesse sur un grand nombre de validations successives.
     *
     * Entrée : 5 000 validations alternant payload valide et payload invalide.
     * Résultat attendu : Aucune exception et types de retour stables.
     */
    public function test_it_survives_massive_validation_loop(): void
    {
        for ($index = 0; $index < 5000; ++$index) {
            $payload = $index % 2 === 0
                ? [
                    'logs' => [
                        [
                            'message' => 'ok-' . $index,
                        ],
                    ],
                ]
                : [
                    'logs' => [
                        'invalid-entry-' . $index,
                    ],
                ];

            $error = $this->validator->validate($payload);

            if ($index % 2 === 0) {
                self::assertNull($error);

                continue;
            }

            self::assertSame(
                sprintf(
                    'Each log entry must be an object. Invalid entry at index %d.',
                    0,
                ),
                $error,
            );
        }
    }
}
