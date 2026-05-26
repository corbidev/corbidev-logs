<?php

namespace App\Jwt\Security;

use App\Jwt\Entity\ApiClient;
use Symfony\Component\HttpFoundation\Request;

final class HmacValidator
{
    public function __construct(
        private readonly CanonicalRequestBuilder $canonicalBuilder,
        private readonly SecretCrypto $crypto,
    ) {}

    public function isValid(Request $request, ApiClient $client): bool
    {
        $signature = $request->headers->get('X-SIGNATURE');

        $stringToSign = $this->canonicalBuilder->build($request);

        foreach ($client->getSecrets() as $secretEntity) {
            if (!$secretEntity->isActive()) {
                continue;
            }

            $secret = $this->crypto->decrypt($secretEntity->getSecretEncrypted());

            $expected = hash_hmac('sha256', $stringToSign, $secret);

            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
