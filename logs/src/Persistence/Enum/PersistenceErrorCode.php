<?php

declare(strict_types=1);

namespace App\Persistence\Enum;

/**
 * Codes d'erreurs métier de persistence.
 *
 * Responsabilités :
 * - fournir des codes stables
 * - éviter les magic numbers
 * - standardiser les erreurs
 */
enum PersistenceErrorCode: int
{
    case DATABASE_CONNECTION_FAILED = 1000;

    case QUERY_EXECUTION_FAILED = 1001;

    case EMPTY_BATCH = 1002;

    case BATCH_TOO_LARGE = 1003;

    case INVALID_PAYLOAD = 1004;

    case TIMEOUT = 1005;

    case DEADLOCK = 1006;

    case TRANSACTION_FAILED = 1007;
}