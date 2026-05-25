<?php

declare(strict_types=1);

namespace App\ApiToken\Application;

/**
 * Résultat explicite de validation token.
 */
final readonly class ValidateApiTokenResult
{
    private function __construct(
        private bool $accepted,
        private string $reason,
    ) {
    }

    public static function accepted(): self
    {
        return new self(
            accepted: true,
            reason: 'token_active',
        );
    }

    public static function refused(string $reason): self
    {
        return new self(
            accepted: false,
            reason: $reason,
        );
    }

    public function isAccepted(): bool
    {
        return $this->accepted;
    }

    public function isRefused(): bool
    {
        return !$this->accepted;
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}
