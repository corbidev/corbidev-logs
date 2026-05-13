<?php

declare(strict_types=1);

namespace App\Log\Domain\Exception;

use DomainException;

/**
 * Exception levée lorsqu'un LogEntry est invalide.
 *
 * RESPONSABILITÉS :
 * -----------------
 * - signaler un invariant métier violé
 * - fournir des erreurs explicites
 * - garantir des messages stables
 *
 * IMPORTANT :
 * ------------
 * Cette exception appartient au Domain.
 *
 * Elle ne doit jamais :
 * - dépendre de Symfony
 * - dépendre d'une infrastructure
 * - contenir de logique technique
 */
final class InvalidLogEntryException extends DomainException
{
    /**
     * Message vide interdit.
     */
    public static function emptyMessage(): self
    {
        return new self(
            'Log message cannot be empty.',
        );
    }

    /**
     * Message trop long.
     */
    public static function messageTooLong(
        int $maxLength,
    ): self {
        return new self(
            sprintf(
                'Log message exceeds maximum length of %d characters.',
                $maxLength,
            ),
        );
    }

    /**
     * Domaine vide interdit.
     */
    public static function emptyDomain(): self
    {
        return new self(
            'Log domain cannot be empty.',
        );
    }

    /**
     * Domaine trop long.
     */
    public static function domainTooLong(
        int $maxLength,
    ): self {
        return new self(
            sprintf(
                'Log domain exceeds maximum length of %d characters.',
                $maxLength,
            ),
        );
    }

    /**
     * Context invalide.
     */
    public static function invalidContext(): self
    {
        return new self(
            'Log context is invalid.',
        );
    }

    /**
     * Extra invalide.
     */
    public static function invalidExtra(): self
    {
        return new self(
            'Log extra payload is invalid.',
        );
    }

    /**
     * Warnings ingestion invalides.
     */
    public static function invalidWarnings(): self
    {
        return new self(
            'Log ingestion warnings are invalid.',
        );
    }

    /**
     * Date createdAt invalide.
     */
    public static function invalidCreatedAt(): self
    {
        return new self(
            'Log createdAt date is invalid.',
        );
    }

    /**
     * Date client invalide.
     */
    public static function invalidClientDate(): self
    {
        return new self(
            'Log clientDate is invalid.',
        );
    }
}