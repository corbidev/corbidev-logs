<?php

namespace App\Jwt\Handler;

use App\Jwt\Entity\ApiNonce;
use App\Jwt\Enum\ApiLogType;
use App\Jwt\Repository\ApiClientRepository;
use App\Jwt\Repository\ApiNonceRepository;
use App\Jwt\Repository\ApiLogRepository;
use App\Jwt\Security\CanonicalRequestBuilder;
use App\Jwt\Security\SecretCrypto;
use App\Jwt\Security\HmacAttackDetector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class ValidateHmacHandler
{
    private const TIMESTAMP_TOLERANCE = 300; // 5 minutes

    public function __construct(
        private readonly ApiClientRepository $clientRepository,
        private readonly ApiNonceRepository $nonceRepository,
        private readonly ApiLogRepository $logRepository,
        private readonly CanonicalRequestBuilder $canonicalBuilder,
        private readonly SecretCrypto $crypto,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger, // 🔥 Monolog
        private readonly HmacAttackDetector $attackDetector, // 🔥 Alerting
    ) {}

    public function handle(Request $request): void
    {
        $clientId = $request->headers->get('X-CLIENT-ID');
        $timestamp = $request->headers->get('X-TIMESTAMP');
        $nonce = $request->headers->get('X-NONCE');
        $signature = $request->headers->get('X-SIGNATURE');
        $path = $request->getPathInfo();

        // 🔎 Headers obligatoires
        if (!$clientId || !$timestamp || !$nonce || !$signature) {
            $this->log(ApiLogType::MISSING_HEADERS, $clientId, $request);
            throw new AccessDeniedHttpException('Missing HMAC headers');
        }

        // 🔐 Sécurité path
        if (!str_starts_with($path, '/api/jwt')) {
            $this->log(ApiLogType::INVALID_PATH, $clientId, $request);
            throw new AccessDeniedHttpException('Invalid HMAC path');
        }

        $client = $this->clientRepository->findOneByClientId($clientId);

        if (!$client) {
            $this->log(ApiLogType::UNKNOWN_CLIENT, $clientId, $request);
            throw new AccessDeniedHttpException('Unknown client');
        }

        // ⏱️ Timestamp
        if (!ctype_digit((string)$timestamp) || abs(time() - (int)$timestamp) > self::TIMESTAMP_TOLERANCE) {
            $this->log(ApiLogType::INVALID_TIMESTAMP, $clientId, $request);
            throw new AccessDeniedHttpException('Invalid timestamp');
        }

        // 🔁 Nonce (anti-replay)
        if ($this->nonceRepository->exists($nonce)) {
            $this->log(ApiLogType::REPLAY, $clientId, $request);
            throw new AccessDeniedHttpException('Replay detected');
        }

        // 🔐 Construction signature
        try {
            $stringToSign = $this->canonicalBuilder->build($request);
        } catch (\InvalidArgumentException) {
            $this->log(ApiLogType::INVALID_PATH, $clientId, $request);
            throw new AccessDeniedHttpException('Invalid canonical request');
        }

        $valid = false;

        foreach ($client->getSecrets() as $secretEntity) {
            if (!$secretEntity->isActive()) {
                continue;
            }

            try {
                $secret = $this->crypto->decrypt($secretEntity->getSecretEncrypted());
            } catch (\Throwable) {
                continue;
            }

            $expected = hash_hmac('sha256', $stringToSign, $secret);

            if (hash_equals($expected, $signature)) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            $this->log(ApiLogType::INVALID_SIGNATURE, $clientId, $request);
            throw new AccessDeniedHttpException('Invalid signature');
        }

        // 💾 stocker nonce
        $nonceEntity = new ApiNonce(
            $nonce,
            $clientId,
            new \DateTimeImmutable('+' . self::TIMESTAMP_TOLERANCE . ' seconds')
        );

        $this->em->persist($nonceEntity);
        $this->em->flush();
    }

    /**
     * 🔥 Centralisation du logging + alerting
     */
    private function log(ApiLogType $type, ?string $clientId, Request $request): void
    {
        // DB (audit)
        $this->logRepository->create(
            $type,
            $clientId,
            $request->getClientIp(),
            $request->getPathInfo()
        );

        // Monolog (business)
        $this->logger->warning('HMAC security event', [
            'type' => $type->value,
            'client_id' => $clientId,
            'ip' => $request->getClientIp(),
            'path' => $request->getPathInfo(),
            'method' => $request->getMethod(),
        ]);

        // 🔥 Détection attaque
        $this->attackDetector->track(
            $clientId ?? 'unknown',
            $type->value
        );
    }
}
