<?php

declare(strict_types=1);

namespace App\Log\Domain\ValueObject;

use App\Log\Enum\IngestionWarningType;
use JsonSerializable;

/**
 * Warning produit pendant l'ingestion.
 *
 * RESPONSABILITÉS :
 * -----------------
 * - tracer les corrections ingestion
 * - tracer les normalisations
 * - conserver les anomalies récupérables
 * - fournir une structure immutable
 *
 * IMPORTANT :
 * ------------
 * Un warning ingestion :
 * - n'est PAS une erreur fatale
 * - ne doit jamais casser l'ingestion
 * - doit rester sérialisable
 * - doit rester borné
 *
 * OBJECTIFS :
 * -----------
 * - robustesse
 * - auditabilité
 * - observabilité
 * - analytics
 * - stabilité long terme
 */
final readonly class IngestionWarning implements JsonSerializable
{
    /**
     * Taille maximale des valeurs sérialisées.
     */
    private const MAX_VALUE_LENGTH = 500;

    /**
     * Constructeur immutable.
     */
    public function __construct(
        private string $field,
        private IngestionWarningType $type,
        private mixed $original = null,
        private mixed $fallback = null,
    ) {
    }

    /**
     * Champ concerné.
     */
    public function field(): string
    {
        return $this->field;
    }

    /**
     * Type du warning.
     */
    public function type(): IngestionWarningType
    {
        return $this->type;
    }

    /**
     * Valeur originale reçue.
     */
    public function original(): mixed
    {
        return $this->original;
    }

    /**
     * Valeur fallback utilisée.
     */
    public function fallback(): mixed
    {
        return $this->fallback;
    }

    /**
     * Retourne une structure sérialisable stable.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'type' => $this->type->value,
            'original' => $this->normalizeValue(
                $this->original,
            ),
            'fallback' => $this->normalizeValue(
                $this->fallback,
            ),
        ];
    }

    /**
     * Sérialisation JSON stable.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Normalise une valeur sérialisable.
     */
    private function normalizeValue(
        mixed $value,
    ): mixed {
        if ($value === null) {
            return null;
        }

        if (
            is_bool($value)
            || is_int($value)
        ) {
            return $value;
        }

        if (is_float($value)) {
            if (
                is_nan($value)
                || is_infinite($value)
            ) {
                return null;
            }

            return $value;
        }

        if (is_string($value)) {
            return mb_substr(
                trim($value),
                0,
                self::MAX_VALUE_LENGTH,
            );
        }

        if ($value instanceof \Stringable) {
            return mb_substr(
                trim((string) $value),
                0,
                self::MAX_VALUE_LENGTH,
            );
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(
                \DateTimeInterface::ATOM,
            );
        }

        if (is_array($value)) {
            return '[array]';
        }

        if (is_resource($value)) {
            return '[unsupported]';
        }

        if (is_object($value)) {
            return sprintf(
                '[object:%s]',
                $value::class,
            );
        }

        return '[unsupported]';
    }
}
