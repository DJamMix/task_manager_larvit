<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
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

        $subscriptions = PushSubscription::query()->where('user_id', $user->id)->get();
        if ($subscriptions->isEmpty()) {
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

            $payload = json_encode([
                'title' => $title,
                'body' => $message,
                'url' => $url,
                'icon' => config('webpush.icon'),
                'tag' => 'tml-' . md5($url),
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
                    Log::debug('WebPush fail', [
                        'user_id' => $user->id,
                        'reason' => $report->getReason(),
                    ]);
                }
                if ($report->isSubscriptionExpired()) {
                    PushSubscription::query()
                        ->where('endpoint', $report->getRequest()->getUri()->__toString())
                        ->delete();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('WebPush exception: ' . $e->getMessage());
        }
    }
}
