<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Bot;

use App\Models\Bot;
use App\Services\BotService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Picture;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class BotEditScreen extends Screen
{
    public $bot;

    public ?string $plain_token = null;

    public function query(Bot $bot, Request $request): iterable
    {
        $this->bot = $bot;
        $this->plain_token = $request->session()->pull('bot_plain_token');

        if ($bot->exists) {
            $bot->loadMissing('user');
            $bot->setAttribute('avatar_path', $bot->user?->avatar_path);
            $bot->setAttribute(
                'commands_json',
                json_encode($bot->commands ?: [
                    ['command' => 'start', 'description' => 'Запуск'],
                    ['command' => 'help', 'description' => 'Справка'],
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            );
        } else {
            $bot->setAttribute(
                'commands_json',
                json_encode([
                    ['command' => 'start', 'description' => 'Запуск'],
                    ['command' => 'help', 'description' => 'Справка'],
                    ['command' => 'status', 'description' => 'Статус сервиса'],
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            );
        }

        $chats = collect();
        if ($bot->exists && $bot->user_id) {
            $chats = \App\Models\Chat::query()
                ->whereHas('members', fn ($q) => $q->where('users.id', $bot->user_id))
                ->orderBy('title')
                ->get(['id', 'title', 'type']);
        }

        return [
            'bot' => $bot,
            'plain_token' => $this->plain_token,
            'bot_chats' => $chats,
            'api_base' => url('/api/bot'),
        ];
    }

    public function name(): ?string
    {
        return $this->bot?->exists ? 'Бот '.$this->bot->name : 'Новый бот';
    }

    public function description(): ?string
    {
        return 'Настройка бота и токена Bot API';
    }

    public function permission(): ?iterable
    {
        return ['platform.systems.bots'];
    }

    public function commandBar(): iterable
    {
        return [
            Link::make('К списку')
                ->icon('bs.arrow-left')
                ->route('platform.systems.bots'),

            Link::make('API docs')
                ->icon('bs.book')
                ->route('platform.systems.bots.docs'),

            Button::make('Перевыпустить токен')
                ->icon('bs.key')
                ->confirm('Старый токен перестанет работать. Продолжить?')
                ->method('regenerateToken')
                ->canSee((bool) $this->bot?->exists),

            Button::make('Удалить')
                ->icon('bs.trash3')
                ->confirm('Удалить бота и его пользователя?')
                ->method('remove')
                ->canSee((bool) $this->bot?->exists),

            Button::make('Сохранить')
                ->icon('bs.check-circle')
                ->method('save'),
        ];
    }

    public function layout(): iterable
    {
        $tokenAlert = $this->plain_token
            ? Layout::view('orchid.layouts.bot-token-alert', ['token' => $this->plain_token, 'api_base' => url('/api/bot')])
            : Layout::view('orchid.layouts.bot-token-hint', [
                'bot' => $this->bot,
                'api_base' => url('/api/bot'),
            ]);

        return [
            $tokenAlert,
            Layout::rows([
                Picture::make('bot.avatar_path')
                    ->title('Аватар')
                    ->storage('public')
                    ->path('avatars/bots')
                    ->targetRelativeUrl()
                    ->acceptedFiles('image/jpeg,image/png,image/webp,image/gif')
                    ->help('Фото бота в чатах и списках. Если нет — показываются инициалы.'),
                Input::make('bot.name')
                    ->title('Имя бота')
                    ->required()
                    ->maxlength(128)
                    ->help('Как в Telegram: отображаемое имя'),
                Input::make('bot.username')
                    ->title('Username')
                    ->required()
                    ->maxlength(32)
                    ->disabled((bool) $this->bot?->exists)
                    ->help('Латиница, цифры, _. Рекомендуется оканчивать на bot. После создания не меняется.'),
                TextArea::make('bot.description')
                    ->title('Описание')
                    ->rows(3)
                    ->maxlength(1000),
                CheckBox::make('bot.is_active')
                    ->title('Активен')
                    ->sendTrueOrFalse()
                    ->value($this->bot?->exists ? $this->bot->is_active : true),
                CheckBox::make('bot.can_join_groups')
                    ->title('Может вступать в группы')
                    ->sendTrueOrFalse()
                    ->value($this->bot?->exists ? $this->bot->can_join_groups : true),
                CheckBox::make('bot.can_read_messages')
                    ->title('Получать сообщения из чатов (updates)')
                    ->sendTrueOrFalse()
                    ->value($this->bot?->exists ? $this->bot->can_read_messages : true),
                Input::make('bot.webhook_url')
                    ->title('Webhook URL')
                    ->type('url')
                    ->help('Если задан — обновления шлются POST на этот URL (как setWebhook в Telegram)'),
                Input::make('bot.webhook_secret')
                    ->title('Webhook secret')
                    ->help('Заголовок X-Bot-Api-Secret-Token'),
                TextArea::make('bot.commands_json')
                    ->title('Команды бота (JSON)')
                    ->rows(6)
                    ->help('Как setMyCommands в Telegram. Пример: [{"command":"start","description":"Запуск"},{"command":"status","description":"Статус"}]'),
            ])->title('Профиль'),

            Layout::rows([
                Input::make('channel.title')
                    ->title('Название чата')
                    ->placeholder('Уведомления · DeployBot'),
                TextArea::make('channel.description')
                    ->title('Описание')
                    ->rows(2),
                Button::make('Создать чат и добавить бота')
                    ->method('createChannel')
                    ->icon('bs.chat-dots')
                    ->class('btn btn-secondary')
                    ->canSee((bool) $this->bot?->exists),
            ])->title('Сервисный чат')->canSee((bool) $this->bot?->exists),

            Layout::view('orchid.layouts.bot-chats-list'),
        ];
    }

    public function save(Request $request, Bot $bot, BotService $bots)
    {
        $data = $request->validate([
            'bot.name' => 'required|string|max:128',
            'bot.username' => [
                Rule::requiredIf(! $bot->exists),
                'nullable',
                'string',
                'max:32',
            ],
            'bot.description' => 'nullable|string|max:1000',
            'bot.avatar_path' => 'nullable|string|max:500',
            'bot.is_active' => 'nullable|boolean',
            'bot.can_join_groups' => 'nullable|boolean',
            'bot.can_read_messages' => 'nullable|boolean',
            'bot.webhook_url' => 'nullable|url|max:500',
            'bot.webhook_secret' => 'nullable|string|max:128',
            'bot.commands_json' => 'nullable|string|max:5000',
        ])['bot'];

        if (! empty($data['commands_json'])) {
            $decoded = json_decode($data['commands_json'], true);
            if (! is_array($decoded)) {
                Toast::error('Команды: невалидный JSON');

                return back()->withInput();
            }
            $data['commands'] = $decoded;
        }
        unset($data['commands_json']);

        if (! $bot->exists) {
            $created = $bots->create($request->user(), $data);
            if (isset($data['commands'])) {
                $bots->setMyCommands($created['bot'], $data['commands']);
            }
            $request->session()->flash('bot_plain_token', $created['token']);
            Toast::success('Бот создан. Скопируйте токен — он показывается один раз.');

            return redirect()->route('platform.systems.bots.edit', $created['bot']);
        }

        $bots->update($bot, $request->user(), $data);
        if (array_key_exists('commands', $data)) {
            $bots->setMyCommands($bot, $data['commands'] ?? []);
        }
        Toast::info('Сохранено');

        return redirect()->route('platform.systems.bots.edit', $bot);
    }

    public function regenerateToken(Request $request, Bot $bot, BotService $bots)
    {
        abort_unless($bot->exists, 404);
        $result = $bots->regenerateToken($bot, $request->user());
        $request->session()->flash('bot_plain_token', $result['token']);
        Toast::warning('Токен перевыпущен');

        return redirect()->route('platform.systems.bots.edit', $bot);
    }

    public function createChannel(Request $request, Bot $bot, BotService $bots)
    {
        abort_unless($bot->exists, 404);
        $data = $request->validate([
            'channel.title' => 'required|string|max:120',
            'channel.description' => 'nullable|string|max:1000',
        ])['channel'];

        $chat = $bots->createBotChannel(
            $request->user(),
            $bot,
            $data['title'],
            $data['description'] ?? null
        );

        Toast::success('Чат создан, бот добавлен. ID чата: '.$chat->id);

        return redirect()->route('platform.systems.bots.edit', $bot);
    }

    public function remove(Request $request, Bot $bot, BotService $bots)
    {
        abort_unless($bot->exists, 404);
        $bots->delete($bot, $request->user());
        Toast::info('Бот удалён');

        return redirect()->route('platform.systems.bots');
    }
}
