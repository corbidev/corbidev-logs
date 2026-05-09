<?php

declare(strict_types=1);

namespace App\Log\Application\Normalizer;

/**
 * Centralise les constantes techniques
 * du système de normalisation.
 *
 * Responsabilités :
 * - limites mémoire,
 * - limites payload,
 * - sécurité,
 * - bornes techniques,
 * - messages système.
 *
 * Cette classe :
 * - ne contient aucune logique métier,
 * - ne contient aucun état,
 * - centralise uniquement la configuration technique.
 */
final class LogNormalizerConfig
{
    /**
     * Taille maximale des chaînes.
     */
    public const MAX_STRING_LENGTH = 1000;

    /**
     * Taille maximale des URI.
     */
    public const MAX_URI_LENGTH = 2000;

    /**
     * Nombre maximal d'éléments
     * par tableau.
     */
    public const MAX_ARRAY_ITEMS = 50;

    /**
     * Profondeur maximale
     * de récursion.
     */
    public const MAX_DEPTH = 5;

    /**
     * Nombre maximal
     * de frames de stacktrace.
     */
    public const MAX_TRACE_FRAMES = 50;

    /**
     * Longueur maximale
     * du requestId.
     */
    public const MAX_REQUEST_ID_LENGTH = 100;

    /**
     * Clés sensibles à filtrer.
     *
     * @var array<string>
     */
    public const SENSITIVE_KEYS = [
        'password',
        'passwd',
        'pwd',
        'token',
        'authorization',
        'cookie',
        'set-cookie',
        'secret',
        'api-key',
        'apikey',
    ];

    /**
     * Message utilisé lorsque
     * la profondeur maximale
     * est atteinte.
     */
    public const MAX_DEPTH_MESSAGE = '[MAX_DEPTH]';

    /**
     * Message utilisé pour
     * les données filtrées.
     */
    public const FILTERED_MESSAGE = '[FILTERED]';

    /**
     * Message utilisé pour
     * les types invalides.
     */
    public const INVALID_TYPE_MESSAGE = '[INVALID_TYPE]';

    /**
     * Message utilisé pour
     * les objets non sérialisables.
     */
    public const OBJECT_MESSAGE_TEMPLATE = '[OBJECT %s]';

    /**
     * Message fallback
     * du message principal.
     */
    public const DEFAULT_MESSAGE = 'Unknown error';

    /**
     * Domaine fallback.
     */
    public const DEFAULT_DOMAIN = 'unknown';

    /**
     * Environment fallback.
     */
    public const DEFAULT_ENV = 'prod';

    /**
     * Client fallback.
     */
    public const DEFAULT_CLIENT = 'unknown-client';

    /**
     * Status HTTP fallback.
     */
    public const DEFAULT_HTTP_STATUS = 500;

    /**
     * URI fallback.
     */
    public const DEFAULT_URI = '/';

    /**
     * Préfixe requestId.
     */
    public const REQUEST_ID_PREFIX = 'req_';
}