<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    private bool $keysBootstrapped = false;

    public function isConfigured(): bool
    {
        $this->bootstrapKeys();

        return (string) config('webpush.public_key') !== ''
            && (string) config('webpush.private_key') !== ''
            && (string) config('webpush.subject') !== '';
    }

    public function publicKey(): string
    {
        $this->bootstrapKeys();

        return (string) config('webpush.public_key');
    }

    /**
     * Создаёт/подхватывает VAPID-ключи, если в .env их ещё нет.
     */
    public function bootstrapKeys(): void
    {
        if ($this->keysBootstrapped) {
            return;
        }
        $this->keysBootstrapped = true;

        if ((string) config('webpush.public_key') !== '' && (string) config('webpush.private_key') !== '') {
            return;
        }

        $path = storage_path('app/webpush-vapid.json');
        if (is_file($path)) {
            $data = json_decode((string) file_get_contents($path), true);
            if (is_array($data) && !empty($data['publicKey']) && !empty($data['privateKey'])) {
                config([
                    'webpush.public_key' => (string) $data['publicKey'],
                    'webpush.private_key' => (string) $data['privateKey'],
                    'webpush.subject' => (string) ($data['subject'] ?? config('webpush.subject')),
                ]);

                return;
            }
        }

        try {
            $keys = VAPID::createVapidKeys();
            $this->persistKeys($path, [
                'publicKey' => $keys['publicKey'],
                'privateKey' => $keys['privateKey'],
                'subject' => (string) config('webpush.subject', 'mailto:admin@crewdev.ru'),
            ]);

            return;
        } catch (\Throwable $e) {
            Log::warning('WebPush: PHP VAPID generate failed: ' . $e->getMessage());
        }

        try {
            $payload = $this->generateKeysViaNode($path);
            if ($payload !== null) {
                config([
                    'webpush.public_key' => $payload['publicKey'],
                    'webpush.private_key' => $payload['privateKey'],
                    'webpush.subject' => $payload['subject'],
                ]);
                Log::info('WebPush: VAPID keys generated via Node at storage/app/webpush-vapid.json');
            }
        } catch (\Throwable $e) {
            Log::warning('WebPush: cannot generate VAPID keys: ' . $e->getMessage());
        }
    }

    /**
     * @param  array{publicKey:string,privateKey:string,subject:string}  $payload
     */
    private function persistKeys(string $path, array $payload): void
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        @chmod($path, 0600);
        config([
            'webpush.public_key' => $payload['publicKey'],
            'webpush.private_key' => $payload['privateKey'],
            'webpush.subject' => $payload['subject'],
        ]);
        Log::info('WebPush: VAPID keys generated at storage/app/webpush-vapid.json');
    }

    /**
     * Fallback, когда PHP OpenSSL не умеет создать EC-ключ (часто на Windows/OSPanel).
     *
     * @return array{publicKey:string,privateKey:string,subject:string}|null
     */
    private function generateKeysViaNode(string $path): ?array
    {
        $script = base_path('scripts/generate-vapid.mjs');
        if (!is_file($script)) {
            return null;
        }

        $subject = (string) config('webpush.subject', 'mailto:admin@crewdev.ru');
        $cmd = [
            'node',
            $script,
            $path,
            $subject,
        ];

        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($cmd, $descriptor, $pipes, base_path(), null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            return null;
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        if ($code !== 0 || !is_file($path)) {
            throw new \RuntimeException(trim($stderr ?: $stdout ?: 'node generate-vapid failed'));
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data) || empty($data['publicKey']) || empty($data['privateKey'])) {
            throw new \RuntimeException('Invalid VAPID JSON written by Node');
        }

        return [
            'publicKey' => (string) $data['publicKey'],
            'privateKey' => (string) $data['privateKey'],
            'subject' => (string) ($data['subject'] ?? $subject),
        ];
    }

    public function send(User $user, string $title, string $message, string $url): void
    {
        if (!$this->isConfigured()) {
            Log::debug('WebPush skipped: not configured');
            return;
        }

        $subscriptions = PushSubscription::query()->where('user_id', $user->id)->get();
        if ($subscriptions->isEmpty()) {
            Log::debug('WebPush skipped: no subscriptions', ['user_id' => $user->id]);
            return;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => config('webpush.subject'),
                    'publicKey' => config('webpush.public_key'),
                    'privateKey' => config('webpush.private_key'),
                ],
            ]);
            $webPush->setReuseVAPIDHeaders(true);
            $webPush->setAutomaticPadding(true);

            $payload = json_encode([
                'title' => $title,
                'body' => $message,
                'url' => $url,
                'icon' => config('webpush.icon'),
                'tag' => 'tml-' . md5($url . '|' . $title),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            foreach ($subscriptions as $subscription) {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $subscription->endpoint,
                        'publicKey' => $subscription->public_key,
                        'authToken' => $subscription->auth_token,
                    ]),
                    $payload
                );
            }

            foreach ($webPush->flush() as $report) {
                if (!$report->isSuccess()) {
                    Log::warning('WebPush fail', [
                        'user_id' => $user->id,
                        'reason' => $report->getReason(),
                        'expired' => $report->isSubscriptionExpired(),
                    ]);
                }
                if ($report->isSubscriptionExpired()) {
                    PushSubscription::query()
                        ->where('endpoint', $report->getRequest()->getUri()->__toString())
                        ->delete();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('WebPush exception: ' . $e->getMessage(), [
                'user_id' => $user->id,
            ]);
        }
    }
}
