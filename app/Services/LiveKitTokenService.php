<?php

namespace App\Services;

use RuntimeException;

/**
 * JWT для LiveKit Access Token (HS256), без внешних зависимостей.
 * Медиа шифруется DTLS-SRTP; опционально E2EE-ключ передаётся клиенту по HTTPS.
 */
class LiveKitTokenService
{
    public function isConfigured(): bool
    {
        return filled(config('livekit.url'))
            && filled(config('livekit.api_key'))
            && filled(config('livekit.api_secret'));
    }

    public function wsUrl(): string
    {
        return rtrim((string) config('livekit.url'), '/');
    }

    /**
     * @param  array{room: string, identity: string, name?: string, metadata?: array<string, mixed>, can_publish?: bool, can_subscribe?: bool, ttl?: int}  $opts
     */
    public function createAccessToken(array $opts): string
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('LiveKit не настроен (LIVEKIT_URL / LIVEKIT_API_KEY / LIVEKIT_API_SECRET).');
        }

        $apiKey = (string) config('livekit.api_key');
        $apiSecret = (string) config('livekit.api_secret');
        $ttl = (int) ($opts['ttl'] ?? config('livekit.token_ttl', 7200));
        $now = time();

        $videoGrant = [
            'roomJoin' => true,
            'room' => $opts['room'],
            'canPublish' => $opts['can_publish'] ?? true,
            'canSubscribe' => $opts['can_subscribe'] ?? true,
            'canPublishData' => true,
        ];

        $meta = array_merge([
            'user_id' => $opts['identity'],
        ], $opts['metadata'] ?? []);

        $payload = [
            'iss' => $apiKey,
            'sub' => $opts['identity'],
            'nbf' => $now - 10,
            'exp' => $now + max(60, $ttl),
            'name' => $opts['name'] ?? $opts['identity'],
            'video' => $videoGrant,
            'metadata' => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ];

        return $this->encodeJwt($payload, $apiSecret);
    }

    private function encodeJwt(array $payload, string $secret): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $segments = [
            $this->b64(json_encode($header, JSON_THROW_ON_ERROR)),
            $this->b64(json_encode($payload, JSON_THROW_ON_ERROR)),
        ];
        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, $secret, true);
        $segments[] = $this->b64($signature);

        return implode('.', $segments);
    }

    private function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
