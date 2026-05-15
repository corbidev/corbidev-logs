<?php

declare(strict_types=1);

namespace App\Log\Domain\ValueObject;

use App\Log\Domain\Exception\InvalidRequestIdException;
use Symfony\Component\Uid\Uuid;

/**
 * Représente un identifiant de corrélation de requête.
 *
 * RESPONSABILITÉS :
 * -----------------
 * - encapsuler un requestId valide
 * - garantir une valeur stable
 * - normaliser les données externes
 * - empêcher les valeurs incohérentes
 *
 * OBJECTIFS :
 * -----------
 * - robustesse
 * - prédictibilité
 * - sécurité
 * - stabilité long terme
 *
 * RÈGLES :
 * --------
 * - toujours non vide
 * - trim automatique
 * - lowercase automatique
 * - caractères sûrs uniquement
 * - taille bornée
 * - auto-génération possible
 *
 * EXEMPLES VALIDES :
 * ------------------
 * - req_8f5c1a
 * - api-request-123
 * - trace_abc_789
 * - 9f6f2a1b
 */
final readonly class RequestId
{
    /**
     * Longueur minimale.
     */
    private const MIN_LENGTH = 3;

    /**
     * Longueur maximale.
     */
    private const MAX_LENGTH = 100;

    /**
     * Pattern autorisé.
     */
    private const REGEX
        = '/^[a-z0-9\-_]+$/';

    /**
     * Valeur normalisée.
     */
    private string $value;

    /**
     * @throws InvalidRequestIdException
     */
    public function __construct(
        string $value,
    ) {
        $value = $this->normalize(
            $value,
        );

        $this->guard(
            $value,
        );

        $this->value = $value;
    }

    /**
     * Génère automatiquement un requestId robuste.
     */
    public static function generate(): self
    {
        return new self(
            sprintf(
                'req_%s',
                bin2hex(
                    random_bytes(8),
                ),
            ),
        );
    }

    /**
     * Crée un RequestId.
     *
     * Si la valeur est vide :
     * - génération automatique
     *
     * @throws InvalidRequestIdException
     */
    public static function fromNullable(
        ?string $value,
    ): self {
        $value = trim(
            (string) $value,
        );

        if ($value === '') {
            return self::generate();
        }

        return new self(
            $value,
        );
    }

    /**
     * Retourne la valeur brute.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Vérifie l'égalité métier.
     */
    public function equals(
        self $other,
    ): bool {
        return hash_equals(
            $this->value,
            $other->value,
        );
    }

    /**
     * Conversion string.
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Vérifie les invariants.
     *
     * @throws InvalidRequestIdException
     */
    private function guard(
        string $value,
    ): void {
        if ($value === '') {
            throw InvalidRequestIdException::empty();
        }

        $length = mb_strlen(
            $value,
        );

        if (
            $length
            < self::MIN_LENGTH
        ) {
            throw InvalidRequestIdException::tooShort(
                self::MIN_LENGTH,
            );
        }

        if (
            $length
            > self::MAX_LENGTH
        ) {
            throw InvalidRequestIdException::tooLong(
                self::MAX_LENGTH,
            );
        }

        if (
            preg_match(
                self::REGEX,
                $value,
            ) !== 1
        ) {
            throw InvalidRequestIdException::invalidFormat();
        }
    }

    /**
     * Normalise la valeur externe.
     */
    private function normalize(
        string $value,
    ): string {
        $value = trim(
            $value,
        );

        $value = mb_strtolower(
            $value,
        );

        return $value;
    }
}