<?php

declare(strict_types=1);

namespace App\Log\Enum;

/**
 * Types de warnings produits pendant l'ingestion.
 *
 * RESPONSABILITÉS :
 * -----------------
 * - centraliser les types de normalisation
 * - stabiliser les warnings ingestion
 * - éviter les strings magiques
 * - fournir des types exploitables en analytics
 *
 * IMPORTANT :
 * ------------
 * Ces warnings ne représentent PAS
 * des erreurs fatales.
 *
 * Ils indiquent uniquement :
 * - une correction
 * - une normalisation
 * - une donnée hostile
 * - une donnée invalide récupérable
 *
 * OBJECTIF :
 * ----------
 * Garantir :
 * - robustesse ingestion
 * - aucune perte
 * - auditabilité
 * - observabilité
 */
enum IngestionWarningType: string
{
    /**
     * Message tronqué.
     */
    case MESSAGE_TRUNCATED = 'message_truncated';

    /**
     * Domaine corrigé.
     */
    case DOMAIN_NORMALIZED = 'domain_normalized';

    /**
     * Niveau invalide remplacé.
     */
    case INVALID_LEVEL = 'invalid_level';

    /**
     * Environnement invalide remplacé.
     */
    case INVALID_ENVIRONMENT = 'invalid_environment';

    /**
     * HTTP status invalide remplacé.
     */
    case INVALID_HTTP_STATUS = 'invalid_http_status';

    /**
     * URI invalide remplacée.
     */
    case INVALID_URI = 'invalid_uri';

    /**
     * URI tronquée.
     */
    case URI_TRUNCATED = 'uri_truncated';

    /**
     * Méthode HTTP invalide remplacée.
     */
    case INVALID_METHOD = 'invalid_method';

    /**
     * Méthode HTTP tronquée.
     */
    case METHOD_TRUNCATED = 'method_truncated';

    /**
     * User-Agent tronqué.
     */
    case USER_AGENT_TRUNCATED = 'user_agent_truncated';

    /**
     * IP invalide remplacée.
     */
    case INVALID_IP = 'invalid_ip';

    /**
     * RequestId invalide régénéré.
     */
    case INVALID_REQUEST_ID = 'invalid_request_id';

    /**
     * Fingerprint régénéré.
     */
    case FINGERPRINT_REGENERATED = 'fingerprint_regenerated';

    /**
     * Client invalide remplacé.
     */
    case INVALID_CLIENT = 'invalid_client';

    /**
     * Date client invalide supprimée.
     */
    case INVALID_CLIENT_DATE = 'invalid_client_date';

    /**
     * Date createdAt invalide supprimée.
     */
    case INVALID_CREATED_AT = 'invalid_created_at';

    /**
     * Context tronqué.
     */
    case CONTEXT_TRUNCATED = 'context_truncated';

    /**
     * Extra tronqué.
     */
    case EXTRA_TRUNCATED = 'extra_truncated';

    /**
     * Profondeur payload tronquée.
     */
    case PAYLOAD_DEPTH_TRUNCATED = 'payload_depth_truncated';

    /**
     * Nombre d'éléments payload tronqué.
     */
    case PAYLOAD_ITEMS_TRUNCATED = 'payload_items_truncated';

    /**
     * UTF-8 invalide supprimé.
     */
    case INVALID_UTF8_REMOVED = 'invalid_utf8_removed';
}