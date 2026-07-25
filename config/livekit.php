<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LiveKit (групповые звонки)
    |--------------------------------------------------------------------------
    |
    | Медиа: DTLS-SRTP. Сигналинг: WSS. Опционально E2EE ключ комнаты.
    | Self-host: docker compose -f docker-compose.livekit.yml up -d
    | Cloud: https://cloud.livekit.io
    |
    */
    'url' => env('LIVEKIT_URL', ''),
    'api_key' => env('LIVEKIT_API_KEY', ''),
    'api_secret' => env('LIVEKIT_API_SECRET', ''),
    'token_ttl' => (int) env('LIVEKIT_TOKEN_TTL', 7200),
];
