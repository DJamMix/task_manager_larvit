<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    public function isConfigured(): bool
    {
        return (string) config('webpush.public_key') !== ''
            && (string) config('webpush.private_key') !== ''
            && (string) config('webpush.subject') !== '';
    }

    public function send(User $user, string $title, string $message, string $url): void
    {
        if (!$this->isConfigured()) {
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
            $payload = json_encode([
                'title' => $title,
                'body' => $message,
                'url' => $url,
                'icon' => config('webpush.icon'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            foreach (PushSubscription::query()->where('user_id', $user->id)->cursor() as $subscription) {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $subscription->endpoint,
                        'publicKey' => $subscription->public_key,
                        'authToken' => $subscription->auth_token,
                        'contentEncoding' => 'aes128gcm',
                    ]),
                    $payload
                );
            }

            foreach ($webPush->flush() as $report) {
                if ($report->isSubscriptionExpired()) {
                    PushSubscription::query()
                        ->where('endpoint', $report->getRequest()->getUri()->__toString())
                        ->delete();
                }
            }
        } catch (\Throwable) {
            // Web Push не должен прерывать бизнес-действие или уведомление Orchid.
        }
    }
}
