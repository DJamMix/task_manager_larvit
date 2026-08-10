@if(!empty($bot) && $bot->exists)
    <div class="alert alert-light border mb-3">
        <div><strong>API:</strong> <code>{{ $api_base }}{token}/METHOD</code></div>
        <div class="small text-muted mt-1">
            Подсказка токена: <code>{{ $bot->token_hint ?: '—' }}</code>
            · User ID бота: <code>{{ $bot->user_id }}</code>
            · Чтобы увидеть полный токен — «Перевыпустить токен».
        </div>
    </div>
@endif
