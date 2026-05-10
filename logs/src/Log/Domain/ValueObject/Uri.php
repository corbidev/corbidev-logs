<?php

declare(strict_types=1);

namespace App\Log\Domain\ValueObject;

use App\Log\Domain\Exception\InvalidUriException;

/**
 * Représente une URI HTTP normalisée et sécurisée.
 *
 * Responsabilités :
 * - garantir une URI exploitable
 * - normaliser les chemins
 * - supprimer les query strings
 * - stabiliser les données utilisées pour le fingerprint
 * - éviter les données hostiles ou infinies
 *
 * Invariants :
 * - toujours un chemin valide
 * - toujours normalisé
 * - jamais vide
 * - longueur bornée
 * - sans query string
 * - immutable
 */
final readonly class Uri
{
    /**
     * Taille maximale autorisée.
     */
    private const MAX_LENGTH = 2048;

    /**
     * URI fallback utilisée
     * pour ingestion hostile.
     */
    private const FALLBACK_URI = '/';

    /**
     * Valeur URI normalisée.
     */
    private string $value;

    /**
     * @throws InvalidUriException
     */
    public function __construct(
        string $value,
    ) {
        $normalized = $this->normalize($value);

        $this->guard($normalized);

        $this->value = $normalized;
    }

    /**
     * Crée une URI depuis une donnée externe hostile.
     *
     * Règles :
     * - fallback "/"
     * - trim automatique
     * - suppression query string
     * - suppression fragment
     * - nettoyage caractères invalides
     * - aucune exception
     */
    public static function fromExternal(
        mixed $value,
    ): self {
        if (is_string($value) === false) {
            return new self(
                self::FALLBACK_URI,
            );
        }

        try {
            return new self($value);
        } catch (InvalidUriException) {
            return new self(
                self::FALLBACK_URI,
            );
        }
    }

    /**
     * Retourne la valeur normalisée.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Vérifie si l'URI représente la racine.
     */
    public function isRoot(): bool
    {
        return $this->value === '/';
    }

    /**
     * Vérifie si l'URI contient des segments.
     */
    public function hasSegments(): bool
    {
        return $this->value !== '/';
    }

    /**
     * Retourne les segments de chemin.
     *
     * @return list<string>
     */
    public function segments(): array
    {
        if ($this->isRoot()) {
            return [];
        }

        return array_values(
            array_filter(
                explode(
                    '/',
                    trim(
                        $this->value,
                        '/',
                    ),
                ),
                static fn (
                    string $segment,
                ): bool => $segment !== '',
            ),
        );
    }

    /**
     * Compare deux URI.
     */
    public function equals(
        self $other,
    ): bool {
        return $this->value === $other->value;
    }

    /**
     * Représentation string stable.
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * @throws InvalidUriException
     */
    private function guard(
        string $value,
    ): void {
        if ($value === '') {
            throw InvalidUriException::empty();
        }

        if (
            mb_strlen($value)
            > self::MAX_LENGTH
        ) {
            throw InvalidUriException::tooLong(
                self::MAX_LENGTH,
            );
        }

        if (
            str_starts_with(
                $value,
                '/',
            ) === false
        ) {
            throw InvalidUriException::mustStartWithSlash();
        }
    }

    /**
     * Normalise une URI externe hostile.
     *
     * Règles :
     * - trim
     * - suppression query string
     * - suppression fragment
     * - réduction slash multiples
     * - ajout slash racine
     * - suppression slash final
     */
    private function normalize(
        string $value,
    ): string {
        $value = trim($value);

        if ($value === '') {
            return self::FALLBACK_URI;
        }

        /*
         * Corrige les payloads hostiles :
         * - ///orders///create///
         * - ////api////v1////
         *
         * parse_url() considère ces valeurs
         * comme invalides.
         */
        if (
            str_starts_with($value, '//')
            && str_contains($value, '://') === false
        ) {
            $value = preg_replace(
                '#/+#',
                '/',
                $value,
            ) ?? self::FALLBACK_URI;
        }

        $path = parse_url(
            $value,
            PHP_URL_PATH,
        );

        if (
            is_string($path) === false
            || $path === ''
        ) {
            return self::FALLBACK_URI;
        }

        $path = rawurldecode($path);

        $path = preg_replace(
            '#/+#',
            '/',
            $path,
        );

        if (
            $path === null
            || $path === ''
        ) {
            return self::FALLBACK_URI;
        }

        $path = trim($path);

        if (
            str_starts_with(
                $path,
                '/',
            ) === false
        ) {
            $path = '/' . $path;
        }

        return rtrim(
            $path,
            '/',
        ) ?: self::FALLBACK_URI;
    }
}