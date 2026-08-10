<div class="alert alert-warning">
    <strong>Сохраните токен сейчас</strong> — он больше не будет показан целиком.
    <div class="mt-2">
        <code id="bot-plain-token" style="user-select:all;word-break:break-all">{{ $token }}</code>
    </div>
    <div class="mt-2 small text-muted">
        Пример запроса:<br>
        <code>{{ rtrim($api_base, '/') }}{{ $token }}/getMe</code>
        &nbsp;или&nbsp;
        <code>Authorization: Bearer {{ $token }}</code> → <code>{{ $api_base }}/getMe</code>
    </div>
</div>
