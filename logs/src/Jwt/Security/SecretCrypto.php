<?php

namespace App\Jwt\Security;

final class SecretCrypto
{
    private string $key;

    public function __construct(string $appSecret)
    {
        // dérive une clé stable depuis APP_SECRET
        $this->key = sodium_crypto_generichash(
            $appSecret,
            '',
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES
        );
    }

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $cipher = sodium_crypto_secretbox($plain, $nonce, $this->key);

        return base64_encode($nonce . $cipher);
    }

    public function decrypt(string $encrypted): string
    {
        $decoded = base64_decode($encrypted);

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->key);

        if ($plain === false) {
            throw new \RuntimeException('Unable to decrypt secret');
        }

        return $plain;
    }
}
