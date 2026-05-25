<?php

namespace App\Jwt\Security;

use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;

final class HmacAttackDetector
{
    private const WINDOW = 60; // secondes
    private const THRESHOLD = 10;

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
    ) {}

    public function track(string $clientId, string $type): void
    {
        $key = sprintf('hmac_attack_%s_%s', $clientId ?? 'unknown', $type);

        $item = $this->cache->getItem($key);

        $count = $item->isHit() ? $item->get() : 0;
        $count++;

        $item->set($count);
        $item->expiresAfter(self::WINDOW);

        $this->cache->save($item);

        if ($count >= self::THRESHOLD) {
            $this->alert($clientId, $type, $count);
        }
    }

    private function alert(?string $clientId, string $type, int $count): void
    {
        $this->logger->error('🚨 HMAC attack detected', [
            'client_id' => $clientId,
            'type' => $type,
            'count' => $count,
            'window' => self::WINDOW,
        ]);
    }
}