<?php

namespace App\Orchid\Screens\Chat;

use App\Models\Chat;
use App\Models\Task;
use App\Orchid\Layouts\Chat\ChatCreateLayout;
use App\Orchid\Layouts\Chat\ChatMembersLayout;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Layouts\Modal;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class MessengerScreen extends Screen
{
    public $chat;

    public $can_create = false;

    public $staff_options = [];

    public function query(ChatService $chats, Request $request): iterable
    {
        $user = $request->user();

        if (!$chats->canAccessMessenger($user)) {
            abort(403);
        }

        $list = $chats->chatsFor($user);
        $resolved = null;

        $routeChat = $request->route('chat');
        if ($routeChat instanceof Chat && $routeChat->exists) {
            if (!$routeChat->isMember($user->id)) {
                abort(403);
            }
            $resolved = $routeChat->load(['members']);
            $chats->markRead($resolved, $user);
        } elseif (is_numeric($routeChat)) {
            $resolved = Chat::query()->findOrFail((int) $routeChat);
            if (!$resolved->isMember($user->id)) {
                abort(403);
            }
            $resolved->load(['members']);
            $chats->markRead($resolved, $user);
        }

        $messages = $resolved
            ? $resolved->messages()
                ->with(['user', 'parent.user', 'task', 'attachment'])
                ->orderBy('created_at')
                ->limit(200)
                ->get()
            : collect();

        $composerMembers = $resolved?->members
            ?->reject(fn ($u) => (int) $u->id === (int) $user->id)
            ->mapWithKeys(fn ($u) => [$u->id => $u->displayName()])
            ->all() ?? [];

        $composerTasks = Task::query()
            ->orderByDesc('id')
            ->limit(80)
            ->get()
            ->mapWithKeys(fn (Task $t) => [$t->id => "#{$t->id} · {$t->name}"])
            ->all();

        return [
            'chats' => $list,
            'chat' => $resolved,
            'messages' => $messages,
            'can_create' => $chats->canCreate($user),
            'staff_options' => $chats->staffUserOptions($user->id),
            'active_chat_id' => $resolved?->id,
            'composer_members' => $composerMembers,
            'composer_tasks' => $composerTasks,
        ];
    }

    public function name(): ?string
    {
        return 'Чаты';
    }

    public function description(): ?string
    {
        return 'Корпоративный мессенджер команды';
    }

    public function permission(): ?iterable
    {
        return ['platform.systems.chats'];
    }

    public function commandBar(): iterable
    {
        $buttons = [];

        if ($this->can_create) {
            $buttons[] = ModalToggle::make('Групповой чат')
                ->modal('createChatModal')
                ->method('createGroup')
                ->icon('bs.plus-lg')
                ->class('btn btn-primary');

            $buttons[] = ModalToggle::make('Личный')
                ->modal('createDirectModal')
                ->method('createDirect')
                ->icon('bs.person');
        }

        if ($this->chat?->exists && $this->chat->type !== 'direct'
            && ($this->chat->isOwner() || $this->can_create)) {
            $buttons[] = ModalToggle::make('Участники')
                ->modal('membersModal')
                ->method('saveMembers')
                ->icon('bs.people');
        }

        return $buttons;
    }

    public function layout(): iterable
    {
        return [
            Layout::view('orchid.layouts.messenger'),

            Layout::modal('createChatModal', [ChatCreateLayout::class])
                ->title('Новый групповой чат')
                ->size(Modal::SIZE_LG)
                ->applyButton('Создать'),

            Layout::modal('createDirectModal', [
                Layout::rows([
                    \Orchid\Screen\Fields\Select::make('direct.user_id')
                        ->options($this->staff_options ?: app(ChatService::class)->staffUserOptions(auth()->id()))
                        ->title('Сотрудник')
                        ->required()
                        ->empty('Выберите'),
                ]),
            ])
                ->title('Личный чат')
                ->applyButton('Открыть'),

            Layout::modal('membersModal', [ChatMembersLayout::class])
                ->title('Участники')
                ->applyButton('Сохранить'),
        ];
    }

    public function createGroup(Request $request, ChatService $chats)
    {
        if (!$chats->canCreate()) {
            abort(403);
        }

        $data = $request->validate([
            'chat.title' => 'required|string|max:120',
            'chat.description' => 'nullable|string|max:1000',
            'chat.member_ids' => 'required|array|min:1',
            'chat.member_ids.*' => 'integer',
        ]);

        $chat = $chats->createGroup(
            $request->user(),
            $data['chat']['title'],
            $data['chat']['member_ids'],
            $data['chat']['description'] ?? null
        );

        Toast::success('Чат создан');

        return redirect()->route('platform.systems.chats.view', $chat);
    }

    public function createDirect(Request $request, ChatService $chats)
    {
        if (!$chats->canCreate()) {
            abort(403);
        }

        $data = $request->validate([
            'direct.user_id' => 'required|integer',
        ]);

        $chat = $chats->findOrCreateDirect($request->user(), (int) $data['direct']['user_id']);

        return redirect()->route('platform.systems.chats.view', $chat);
    }

    public function saveMembers(Request $request, Chat $chat, ChatService $chats)
    {
        $data = $request->validate([
            'chat.member_ids' => 'required|array|min:1',
            'chat.member_ids.*' => 'integer',
        ]);

        $chats->syncMembers($chat, $request->user(), $data['chat']['member_ids']);
        Toast::success('Участники обновлены');

        return redirect()->route('platform.systems.chats.view', $chat);
    }

    public function sendMessage(Request $request, Chat $chat, ChatService $chats)
    {
        $chats->addMessage($chat, $request->user(), $request);
        Toast::success('Отправлено');

        return redirect()->route('platform.systems.chats.view', $chat);
    }
}
