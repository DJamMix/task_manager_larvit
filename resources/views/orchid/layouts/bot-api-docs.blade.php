@php
    $base = rtrim($api_base ?? url('/api/bot'), '/');
    $tok = $example_token ?? '123456789:AAHxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
@endphp
<div class="bg-white rounded shadow-sm p-4 bot-api-docs">
    <style>
        .bot-api-docs h2 { font-size: 1.15rem; margin: 1.5rem 0 0.6rem; }
        .bot-api-docs h3 { font-size: 1rem; margin: 1.1rem 0 0.4rem; }
        .bot-api-docs code, .bot-api-docs pre { background: #0f172a; color: #e2e8f0; border-radius: 8px; }
        .bot-api-docs code { padding: 0.1rem 0.35rem; font-size: 0.85em; }
        .bot-api-docs pre { padding: 0.85rem 1rem; overflow-x: auto; font-size: 0.82rem; line-height: 1.5; }
        .bot-api-docs table { width: 100%; border-collapse: collapse; margin: 0.6rem 0 1rem; font-size: 0.9rem; }
        .bot-api-docs th, .bot-api-docs td { border: 1px solid #e2e8f0; padding: 0.45rem 0.6rem; text-align: left; vertical-align: top; }
        .bot-api-docs th { background: #f8fafc; }
        .bot-api-docs .muted { color: #64748b; }
    </style>

    <p class="muted mb-3">
        API спроектирован по образцу <strong>Telegram Bot API</strong>: токен в URL, JSON-ответы вида
        <code>{"ok":true,"result":…}</code>, методы <code>getMe</code>, <code>sendMessage</code>,
        <code>getUpdates</code> / webhook и т.д.
    </p>

    <h2>1. Авторизация</h2>
    <p>Два равносильных варианта:</p>
    <pre>GET {{ $base }}{{ $tok }}/getMe

GET {{ $base }}/getMe
Authorization: Bearer {{ $tok }}</pre>
    <p class="muted">Токен выдаётся один раз при создании / перевыпуске бота в админке.</p>

    <h2>2. Типичный сценарий «сервис → чат»</h2>
    <ol>
        <li>Админ создаёт бота и копирует токен.</li>
        <li>Админ создаёт сервисный чат на карточке бота (или добавляет бота в группу через «Участники»).</li>
        <li>Внешний сервис вызывает <code>sendMessage</code> с <code>chat_id</code>.</li>
        <li>Опционально бот слушает входящие через <code>getUpdates</code> или webhook.</li>
    </ol>

    <h2>3. Методы</h2>

    <h3>getMe</h3>
    <p>Информация о боте.</p>
    <pre>GET {{ $base }}{token}/getMe</pre>

    <h3>sendMessage</h3>
    <table>
        <tr><th>Параметр</th><th>Тип</th><th>Описание</th></tr>
        <tr><td>chat_id</td><td>int</td><td>ID чата (обязательный)</td></tr>
        <tr><td>text</td><td>string</td><td>Текст сообщения</td></tr>
        <tr><td>reply_to_message_id</td><td>int</td><td>Ответ на сообщение</td></tr>
        <tr><td>disable_notification</td><td>bool</td><td>Без push-уведомлений людям</td></tr>
    </table>
    <pre>curl -X POST "{{ $base }}{token}/sendMessage" \
  -H "Content-Type: application/json" \
  -d '{"chat_id":12,"text":"Deploy #142 OK"}'</pre>

    <h3>sendDocument / sendPhoto</h3>
    <p>multipart/form-data: <code>chat_id</code>, файл <code>document</code> (или <code>photo</code>), опционально <code>caption</code>.</p>
    <pre>curl -X POST "{{ $base }}{token}/sendDocument" \
  -F chat_id=12 \
  -F document=@./report.pdf \
  -F caption="Отчёт"</pre>

    <h3>editMessageText</h3>
    <p><code>chat_id</code>, <code>message_id</code>, <code>text</code> — править только свои сообщения бота.</p>

    <h3>deleteMessage</h3>
    <p><code>chat_id</code>, <code>message_id</code> — удалить своё сообщение бота.</p>

    <h3>forwardMessage</h3>
    <p><code>chat_id</code> (куда), <code>from_chat_id</code>, <code>message_id</code>.</p>

    <h3>getChat</h3>
    <p><code>chat_id</code> → id, type (<code>group</code>/<code>private</code>), title, description.</p>

    <h3>getChatMember / getChatAdministrators</h3>
    <p>Состав и владельцы группы (бот должен быть участником).</p>

    <h3>leaveChat</h3>
    <p><code>chat_id</code> — бот покидает группу.</p>

    <h2>4. Получение обновлений</h2>

    <h3>getUpdates</h3>
    <table>
        <tr><th>Параметр</th><th>Описание</th></tr>
        <tr><td>offset</td><td>update_id ≥ offset</td></tr>
        <tr><td>limit</td><td>1–100</td></tr>
        <tr><td>timeout</td><td>long polling до 25 сек</td></tr>
    </table>
    <pre>GET {{ $base }}{token}/getUpdates?offset=0&amp;timeout=10</pre>
    <p>Формат update:</p>
    <pre>{
  "update_id": 501,
  "message": {
    "message_id": 88,
    "date": 1710000000,
    "chat": {"id": 12, "type": "group", "title": "Алерты"},
    "from": {"id": 5, "is_bot": false, "first_name": "Иван"},
    "text": "привет @deploybot"
  }
}</pre>

    <h3>setWebhook / deleteWebhook / getWebhookInfo</h3>
    <p>
        <code>setWebhook</code>: <code>url</code>, опционально <code>secret_token</code>
        (заголовок <code>X-Bot-Api-Secret-Token</code>).<br>
        Пустой <code>url</code> или <code>deleteWebhook</code> отключает webhook.
    </p>
    <pre>POST {{ $base }}{token}/setWebhook
{"url":"https://example.com/hooks/crewdev","secret_token":"mysecret"}</pre>

    <h2>5. Права</h2>
    <ul>
        <li>Создание и настройка ботов — право <code>platform.systems.bots</code> (роль admin).</li>
        <li>Добавление бота в чат — <code>platform.systems.bots</code> или <code>platform.systems.chats.create</code> + право управлять группой.</li>
        <li>Эта страница документации доступна только с правом на ботов.</li>
    </ul>

    <h2>6. Ошибки</h2>
    <pre>{"ok":false,"error_code":401,"description":"Unauthorized: invalid bot token"}</pre>
</div>
