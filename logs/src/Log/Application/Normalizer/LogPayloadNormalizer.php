<?php

declare(strict_types=1);

namespace App\Log\Application\Normalizer;

use App\Log\Enum\LogLevel;
use DateTimeImmutable;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Normalise un payload de log externe hostile
 * en structure sûre, bornée et prédictible.
 *
 * Responsabilités :
 * - appliquer les fallbacks,
 * - générer les champs système,
 * - filtrer les données sensibles,
 * - limiter profondeur/taille,
 * - stabiliser les données,
 * - sécuriser les objets,
 * - protéger UTF8,
 * - protéger mémoire et récursion,
 * - ne jamais provoquer d'erreur fatale.
 *
 * Ce composant est volontairement :
 * - synchrone,
 * - déterministe,
 * - sans I/O,
 * - ultra testable,
 * - compatible mutualisé.
 */
final class LogPayloadNormalizer
{
    /**
     * @param ClockInterface $clock
     * Horloge injectable pour tests déterministes.
     */
    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Normalise un payload externe hostile.
     *
     * Garanties :
     * - toujours retourner un tableau valide,
     * - jamais d'erreur fatale,
     * - toujours des données cohérentes,
     * - toujours des bornes mémoire.
     *
     * IMPORTANT :
     * Les champs métier critiques utilisent
     * le payload BRUT afin d'éviter les effets
     * de bord de la normalisation générique.
     *
     * @param mixed $payload
     *
     * @return array<string, mixed>
     */
    public function normalize(
        mixed $payload
    ): array {
        if (!is_array($payload)) {
            $payload = [];
        }

        /*
         * Payload générique normalisé.
         *
         * Utilisé uniquement pour :
         * - context
         * - extra
         * - tags
         * - exception
         * - server
         * - runtime
         * - user
         *
         * Les champs métier critiques
         * utilisent toujours le payload brut.
         */
        $normalizedPayload = $this->normalizeArray(
            $payload,
            0
        );

        $request = $this->normalizeRequest(
            $payload['request'] ?? []
        );

        $level = $this->normalizeLevel(
            $payload['level'] ?? null
        );

        $httpStatus = $this->normalizeHttpStatus(
            $payload['httpStatus'] ?? null
        );

        $domain = $this->normalizeString(
            $payload['domain']
                ?? LogNormalizerConfig::DEFAULT_DOMAIN
        );

        $env = $this->normalizeString(
            $payload['env']
                ?? LogNormalizerConfig::DEFAULT_ENV
        );

        return [
            'message' => $this->normalizeString(
                $payload['message']
                    ?? LogNormalizerConfig::DEFAULT_MESSAGE
            ),

            'level' => $level,

            'domain' => $domain,

            'env' => $env,

            'httpStatus' => $httpStatus,

            'client' => $this->normalizeString(
                $payload['client']
                    ?? LogNormalizerConfig::DEFAULT_CLIENT
            ),

            'requestId' => $this->normalizeRequestId(
                $payload['requestId'] ?? null
            ),

            'externalId' => $this->normalizeExternalId(
                $payload['externalId'] ?? null
            ),

            'createdAt' => $this->clock
                ->now()
                ->format(DATE_ATOM),

            'clientDate' => $this->normalizeClientDate(
                $payload['clientDate'] ?? null
            ),

            'fingerprint' => $this->generateFingerprint(
                $level,
                $httpStatus,
                $domain,
                $request['uri'],
                $env
            ),

            'context' => $this->normalizeObject(
                $normalizedPayload['context'] ?? []
            ),

            'extra' => $this->normalizeObject(
                $normalizedPayload['extra'] ?? []
            ),

            'tags' => $this->normalizeObject(
                $normalizedPayload['tags'] ?? []
            ),

            'exception' => $this->normalizeException(
                $normalizedPayload['exception'] ?? []
            ),

            'request' => $request,

            'server' => $this->normalizeObject(
                $normalizedPayload['server'] ?? []
            ),

            'runtime' => $this->normalizeObject(
                $normalizedPayload['runtime'] ?? []
            ),

            'user' => $this->normalizeObject(
                $normalizedPayload['user'] ?? []
            ),
        ];
    }

    /**
     * Normalise récursivement une structure.
     *
     * Garanties :
     * - profondeur bornée,
     * - mémoire bornée,
     * - objets sécurisés,
     * - données sensibles filtrées.
     *
     * @return mixed
     */
    private function normalizeArray(
        mixed $value,
        int $depth
    ): mixed {
        if (
            $depth >= LogNormalizerConfig::MAX_DEPTH
        ) {
            return LogNormalizerConfig::MAX_DEPTH_MESSAGE;
        }

        if (is_object($value)) {
            return $this->normalizeObjectValue(
                $value
            );
        }

        if (!is_array($value)) {
            return $this->normalizeScalar(
                $value
            );
        }

        $result = [];

        $count = 0;

        foreach ($value as $key => $item) {
            if (
                $count
                >= LogNormalizerConfig::MAX_ARRAY_ITEMS
            ) {
                break;
            }

            $normalizedKey = $this->normalizeString(
                $key
            );

            if ($normalizedKey === '') {
                continue;
            }

            if (
                $this->isSensitiveKey(
                    $normalizedKey
                )
            ) {
                $result[$normalizedKey]
                    = LogNormalizerConfig::FILTERED_MESSAGE;

                $count++;

                continue;
            }

            $result[$normalizedKey]
                = $this->normalizeArray(
                    $item,
                    $depth + 1
                );

            $count++;
        }

        return $result;
    }

    /**
     * Normalise une valeur scalaire.
     *
     * @return scalar|null|string
     */
    private function normalizeScalar(
        mixed $value
    ): mixed {
        if (
            is_string($value)
            || is_int($value)
            || is_float($value)
            || is_bool($value)
        ) {
            return $this->normalizeString(
                (string) $value
            );
        }

        if ($value === null) {
            return null;
        }

        return LogNormalizerConfig::INVALID_TYPE_MESSAGE;
    }

    /**
     * Normalise un objet externe.
     *
     * Les objets ne doivent jamais :
     * - provoquer de récursion,
     * - exploser la mémoire,
     * - casser le pipeline.
     */
    private function normalizeObjectValue(
        object $object
    ): string {
        if (
            method_exists(
                $object,
                '__toString'
            )
        ) {
            return $this->normalizeString(
                (string) $object
            );
        }

        return sprintf(
            LogNormalizerConfig::OBJECT_MESSAGE_TEMPLATE,
            $object::class
        );
    }

    /**
     * Normalise une chaîne UTF-8.
     *
     * Garanties :
     * - trim,
     * - UTF8 valide,
     * - taille bornée,
     * - jamais d'erreur fatale.
     */
    private function normalizeString(
        mixed $value
    ): string {
        if (
            !is_scalar($value)
            && $value !== null
        ) {
            return '';
        }

        $value = trim(
            (string) $value
        );

        $value = iconv(
            'UTF-8',
            'UTF-8//IGNORE',
            $value
        );

        if ($value === false) {
            return '';
        }

        if (
            mb_strlen($value)
            > LogNormalizerConfig::MAX_STRING_LENGTH
        ) {
            $value = mb_substr(
                $value,
                0,
                LogNormalizerConfig::MAX_STRING_LENGTH
            );
        }

        return $value;
    }

    /**
     * Normalise un niveau PSR-3.
     */
    private function normalizeLevel(
        mixed $value
    ): string {
        $value = mb_strtolower(
            $this->normalizeString($value)
        );

        return LogLevel::tryFrom($value)
            ?->value
            ?? LogLevel::default()->value;
    }

    /**
     * Normalise un status HTTP.
     */
    private function normalizeHttpStatus(
        mixed $value
    ): int {
        if (!is_numeric($value)) {
            return LogNormalizerConfig::DEFAULT_HTTP_STATUS;
        }

        $value = (int) $value;

        if (
            $value < 100
            || $value > 599
        ) {
            return LogNormalizerConfig::DEFAULT_HTTP_STATUS;
        }

        return $value;
    }

    /**
     * Normalise les données HTTP.
     *
     * @return array<string, mixed>
     */
    private function normalizeRequest(
        mixed $request
    ): array {
        if (!is_array($request)) {
            $request = [];
        }

        return [
            'method' => $this->normalizeString(
                $request['method'] ?? ''
            ),

            'uri' => $this->normalizeUri(
                $request['uri']
                    ?? LogNormalizerConfig::DEFAULT_URI
            ),

            'route' => $this->normalizeString(
                $request['route'] ?? ''
            ),

            'headers' => $this->normalizeObject(
                $request['headers'] ?? []
            ),
        ];
    }

    /**
     * Normalise une URI.
     *
     * Garanties :
     * - uniquement URI HTTP valide,
     * - suppression query strings,
     * - suppression doubles slash,
     * - UTF8 safe,
     * - taille bornée,
     * - fallback stable.
     */
    private function normalizeUri(
        mixed $uri
    ): string {
        if (!is_string($uri)) {
            return LogNormalizerConfig::DEFAULT_URI;
        }

        $uri = trim($uri);

        if ($uri === '') {
            return LogNormalizerConfig::DEFAULT_URI;
        }

        /*
         * Une URI valide doit commencer
         * par "/" afin d'éviter :
         * - scalaires convertis,
         * - données corrompues,
         * - URI invalides.
         */
        if (!str_starts_with($uri, '/')) {
            return LogNormalizerConfig::DEFAULT_URI;
        }

        $uri = explode(
            '?',
            $uri
        )[0];

        $uri = preg_replace(
            '#/+#',
            '/',
            $uri
        );

        if ($uri === null) {
            return LogNormalizerConfig::DEFAULT_URI;
        }

        $uri = iconv(
            'UTF-8',
            'UTF-8//IGNORE',
            $uri
        );

        if ($uri === false) {
            return LogNormalizerConfig::DEFAULT_URI;
        }

        if (
            mb_strlen($uri)
            > LogNormalizerConfig::MAX_URI_LENGTH
        ) {
            $uri = mb_substr(
                $uri,
                0,
                LogNormalizerConfig::MAX_URI_LENGTH
            );
        }

        return $uri === ''
            ? LogNormalizerConfig::DEFAULT_URI
            : $uri;
    }

    /**
     * Normalise une structure objet.
     *
     * @return array<string, mixed>
     */
    private function normalizeObject(
        mixed $value
    ): array {
        if (!is_array($value)) {
            return [];
        }

        return $this->normalizeArray(
            $value,
            0
        );
    }

    /**
     * Normalise une exception.
     *
     * Garanties :
     * - trace bornée,
     * - structure stable,
     * - jamais d'erreur fatale.
     *
     * @return array<string, mixed>
     */
    private function normalizeException(
        mixed $exception
    ): array {
        if (!is_array($exception)) {
            return [];
        }

        $trace = $exception['trace'] ?? [];

        if (!is_array($trace)) {
            $trace = [];
        }

        $trace = array_slice(
            $trace,
            0,
            LogNormalizerConfig::MAX_TRACE_FRAMES
        );

        return [
            'class' => $this->normalizeString(
                $exception['class'] ?? ''
            ),

            'message' => $this->normalizeString(
                $exception['message'] ?? ''
            ),

            'code' => is_numeric(
                $exception['code'] ?? null
            )
                ? (int) $exception['code']
                : 0,

            'file' => $this->normalizeString(
                $exception['file'] ?? ''
            ),

            'line' => is_numeric(
                $exception['line'] ?? null
            )
                ? (int) $exception['line']
                : 0,

            'trace' => $this->normalizeArray(
                $trace,
                0
            ),
        ];
    }

    /**
     * Génère un fingerprint stable.
     *
     * Les segments numériques sont normalisés
     * afin d'améliorer le regroupement des erreurs.
     */
    private function generateFingerprint(
        string $level,
        int $httpStatus,
        string $domain,
        string $uri,
        string $env
    ): string {
        $uri = preg_replace(
            '/\/\d+/',
            '/{id}',
            $uri
        );

        if ($uri === null) {
            $uri = '/';
        }

        $base = mb_strtolower(
            trim(sprintf(
                '%s|%d|%s|%s|%s',
                $level,
                $httpStatus,
                $domain,
                $uri,
                $env
            ))
        );

        return mb_substr(
            sha1($base),
            0,
            16
        );
    }

    /**
     * Normalise un requestId.
     *
     * Si absent :
     * - génération UUID v7.
     */
    private function normalizeRequestId(
        mixed $requestId
    ): string {
        $requestId = preg_replace(
            '/[^a-zA-Z0-9\-_.]/',
            '',
            $this->normalizeString(
                $requestId
            )
        );

        if (
            $requestId === null
            || $requestId === ''
        ) {
            return sprintf(
                '%s%s',
                LogNormalizerConfig::REQUEST_ID_PREFIX,
                Uuid::v7()->toString()
            );
        }

        return mb_substr(
            $requestId,
            0,
            LogNormalizerConfig::MAX_REQUEST_ID_LENGTH
        );
    }

    /**
     * Normalise un externalId UUID.
     *
     * Si invalide :
     * - génération UUID v7.
     */
    private function normalizeExternalId(
        mixed $externalId
    ): string {
        if (
            is_string($externalId)
            && Uuid::isValid($externalId)
        ) {
            return $externalId;
        }

        return Uuid::v7()->toString();
    }

    /**
     * Normalise une date client.
     */
    private function normalizeClientDate(
        mixed $date
    ): ?string {
        if (!is_scalar($date)) {
            return null;
        }

        try {
            return (
                new DateTimeImmutable(
                    (string) $date
                )
            )->format(DATE_ATOM);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Vérifie si une clé est sensible.
     */
    private function isSensitiveKey(
        string $key
    ): bool {
        return in_array(
            mb_strtolower($key),
            LogNormalizerConfig::SENSITIVE_KEYS,
            true
        );
    }
}