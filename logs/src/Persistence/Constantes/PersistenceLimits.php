<?php

declare(strict_types=1);

namespace App\Persistence\Constantes;

/**
 * Centralise les limites techniques
 * de la couche Persistence.
 *
 * Objectifs :
 * - éviter les magic numbers
 * - garantir cohérence globale
 * - simplifier maintenance
 * - rendre les limites explicites
 *
 * IMPORTANT :
 * Cette classe contient uniquement
 * des limites techniques stables.
 */
final class PersistenceLimits
{
    /**
     * Nombre maximal d'erreurs conservées
     * dans PersistenceResult.
     *
     * Protection mémoire.
     */
    public const int MAX_ERRORS = 100;

    /**
     * Taille maximale d'un message d'erreur.
     *
     * Protection contre :
     * - payloads hostiles
     * - pollution mémoire
     * - erreurs gigantesques
     */
    public const int MAX_ERROR_LENGTH = 1000;

    /**
     * Nombre maximal d'éléments
     * dans le contexte d'exception.
     *
     * Protection contre :
     * - explosion mémoire
     * - payloads hostiles
     * - structures massives
     */
    public const int MAX_EXCEPTION_CONTEXT_ITEMS = 20;

    /**
     * Taille maximale d'une clé
     * du contexte d'exception.
     *
     * Protection contre :
     * - clés hostiles
     * - pollution mémoire
     * - corruption logs
     */
    public const int MAX_EXCEPTION_CONTEXT_KEY_LENGTH = 100;

    /**
     * Taille maximale d'une valeur
     * du contexte d'exception.
     *
     * Protection contre :
     * - énormes payloads
     * - injections volumineuses
     * - saturation mémoire
     */
    public const int MAX_EXCEPTION_CONTEXT_VALUE_LENGTH = 500;

    private function __construct()
    {
    }
}