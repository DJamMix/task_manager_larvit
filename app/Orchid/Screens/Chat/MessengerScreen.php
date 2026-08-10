<?php

namespace App\Orchid\Screens\Chat;

use App\Models\Chat;
use App\Orchid\Layouts\Chat\ChatCreateLayout;
use App\Orchid\Layouts\Chat\ChatEditLayout;
use App\Orchid\Layouts\Chat\ChatMembersLayout;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Layouts\Modal;
use Orchid\Screen\Screen;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class MessengerScreen extends Screen
{
    public $chat;

    public $can_create = false;

    public $can_chat_clients = false;

    public $can_write = true;

    public $can_edit_chat = false;

    public $member_options = [];

    public $direct_options = [];

    public function query(ChatService $chats, Request $request): iterable
    {
        // Полноширинный workspace без footer/container-xl — мессенджер до меню и края экрана
        config(['platform.workspace' => 'orchid.workspace.messenger']);

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
        } elseif (is_numeric($routeChat)) {
            $resolved = Chat::query()->findOrFail((int) $routeChat);
            if (!$resolved->isMember($user->id)) {
                abort(403);
            }
            $resolved->load(['members']);
        }

        $focusMessageId = request()->integer('msg') ?: null;
        $firstUnreadId = null;
        if ($resolved && !$focusMessageId) {
            $firstUnreadId = $chats->firstUnreadMessageId($resolved, $user);
            // Открываем ленту вокруг первого непрочитанного, а не всегда в самом низу
            if ($firstUnreadId) {
                $focusMessageId = $firstUnreadId;
            }
        }

        $feedMeta = $resolved
            ? $chats->feedForChat($resolved, $user, $focusMessageId, 40)
            : ['messages' => collect(), 'has_more' => false, 'oldest_id' => null];
        $messages = $feedMeta['messages'];

        if ($resolved && $firstUnreadId === null) {
            $firstUnreadId = $chats->firstUnreadMessageId($resolved, $user);
        }

        $mentionUsers = $resolved?->members
            ?->reject(fn ($u) => (int) $u->id === (int) $user->id)
            ->map(fn ($u) => [
                'id' => (int) $u->id,
                'name' => $u->displayName() ?: $u->name,
                'aliases' => array_values(array_unique(array_filter([
                    $u->name,
                    $u->displayName(),
                    $u->email ? strtok($u->email, '@') : null,
                ]))),
                'avatar_url' => (string) $u->avatarUrl(),
                'avatar_initials' => (string) $u->avatarInitials(),
                'avatar_color' => (string) $u->avatarColor(),
            ])
            ->values()
            ->all() ?? [];

        $composerTasks = $chats->attachableTasksFor($user, null, 60);

        $chats->touchPresence($user);
        $presenceMemberIds = $list
            ->flatMap(fn (Chat $c) => $c->members->pluck('id'))
            ->push($user->id)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $presence = $chats->presenceMap($presenceMemberIds);

        $isMuted = false;
        $isPinned = false;
        $canWrite = true;
        $canEditChat = false;
        if ($resolved) {
            $pivot = $resolved->members->firstWhere('id', $user->id)?->pivot;
            $isMuted = (bool) ($pivot?->is_muted ?? false);
            $isPinned = (bool) ($pivot?->is_pinned ?? false);
            $canEditChat = $resolved->type !== 'direct'
                && ($resolved->isOwner($user->id) || $chats->canCreate($user));

            try {
                $chats->assertCanWriteInChat($resolved, $user);
            } catch (\Throwable) {
                $canWrite = false;
            }
        }

        return [
            'chats' => $list,
            'chat' => $resolved,
            'messages' => $messages,
            'messages_has_more' => (bool) ($feedMeta['has_more'] ?? false),
            'messages_oldest_id' => $feedMeta['oldest_id'] ?? null,
            'can_create' => $chats->canCreate($user),
            'can_chat_clients' => $chats->canChatWithClients($user),
            'can_write' => $canWrite,
            'can_edit_chat' => $canEditChat,
            'member_options' => $chats->chatMemberOptions($user->id),
            'direct_options' => $chats->directInterlocutorOptions($user),
            'staff_options' => $chats->directInterlocutorOptions($user),
            'active_chat_id' => $resolved?->id,
            'mention_users' => $mentionUsers,
            'first_unread_id' => $firstUnreadId,
            'composer_tasks' => $composerTasks,
            'composer_tasks_search_url' => route('platform.systems.chats.tasks'),
            'chat_is_muted' => $isMuted,
            'chat_is_pinned' => $isPinned,
            'presence' => $presence,
            'chats_poll_url' => route('platform.systems.chats.poll'),
            'chats_search_url' => route('platform.systems.chats.search'),
            'chats_typing_url' => $resolved
                ? route('platform.systems.chats.typing', $resolved)
                : null,
            'chats_messages_url' => $resolved
                ? route('platform.systems.chats.messages', $resolved)
                : null,
            'chats_picker_url' => route('platform.systems.chats.picker'),
            'chats_media_url' => $resolved
                ? route('platform.systems.chats.media', $resolved)
                : null,
            'calls_enabled' => app(\App\Services\CallService::class)->isAvailable(),
            'calls_start_url' => $resolved
                ? route('platform.systems.chats.calls.start', $resolved)
                : null,
        ];
    }

    public function name(): ?string
    {
        return null;
    }

    public function description(): ?string
    {
        return null;
    }

    public function permission(): ?iterable
    {
        return ['platform.systems.chats'];
    }

    public function commandBar(): iterable
    {
        // Кнопки спрятаны CSS на странице мессенджера, но нужны в DOM для открытия модалок из сайдбара.
        $buttons = [];

        $buttons[] = ModalToggle::make('Личный')
            ->modal('createDirectModal')
            ->method('createDirect')
            ->icon('bs.person')
            ->class('d-none bx-orchid-modal-toggle');

        if ($this->can_create) {
            $buttons[] = ModalToggle::make('Групповой чат')
                ->modal('createChatModal')
                ->method('createGroup')
                ->icon('bs.plus-lg')
                ->class('d-none bx-orchid-modal-toggle');
        }

        if ($this->chat?->exists && $this->chat->type !== 'direct'
            && ($this->chat->isOwner() || $this->can_create)) {
            $buttons[] = ModalToggle::make('Изменить')
                ->modal('editChatModal')
                ->method('saveChat')
                ->icon('bs.pencil')
                ->class('d-none bx-orchid-modal-toggle');

            $buttons[] = ModalToggle::make('Участники')
                ->modal('membersModal')
                ->method('saveMembers')
                ->icon('bs.people')
                ->class('d-none bx-orchid-modal-toggle');

            $buttons[] = Button::make('Удалить чат')
                ->icon('bs.trash')
                ->method('removeChat')
                ->confirm('Удалить групповой чат безвозвратно? Сообщения, файлы и история звонков будут удалены.')
                ->type(Color::DANGER)
                ->class('d-none bx-orchid-modal-toggle');
        }

        return $buttons;
    }

    public function layout(): iterable
    {
        $directOptions = $this->direct_options
            ?: app(ChatService::class)->directInterlocutorOptions(auth()->user());

        return [
            Layout::view('orchid.layouts.messenger'),

            Layout::modal('createChatModal', [ChatCreateLayout::class])
                ->title('Новый групповой чат')
                ->size(Modal::SIZE_LG)
                ->applyButton('Создать'),

            Layout::modal('editChatModal', [ChatEditLayout::class])
                ->title('Настройки чата')
                ->size(Modal::SIZE_LG)
                ->applyButton('Сохранить'),

            Layout::modal('createDirectModal', [
                Layout::rows([
                    \Orchid\Screen\Fields\Select::make('direct.user_id')
                        ->options($directOptions)
                        ->title('Собеседник')
                        ->help($this->can_chat_clients
                            ? 'Коллега или клиент / контакт клиента'
                            : 'Только сотрудники. Для личных чатов с клиентами нужно право «Чаты с клиентами»')
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
            'chat.avatar_path' => 'nullable|string|max:500',
            'chat.member_ids' => 'required|array|min:1',
            'chat.member_ids.*' => 'integer',
        ]);

        $chat = $chats->createGroup(
            $request->user(),
            $data['chat']['title'],
            $data['chat']['member_ids'],
            $data['chat']['description'] ?? null,
            $data['chat']['avatar_path'] ?? null
        );

        Toast::success('Чат создан');

        return redirect()->route('platform.systems.chats.view', $chat);
    }

    public function createDirect(Request $request, ChatService $chats)
    {
        if (!$chats->canAccessMessenger($request->user())) {
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
        $message = $chats->addMessage($chat, $request->user(), $request);

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            $payload = $chats->renderMessagePayload($chat, $message->fresh([
                'user', 'parent.user', 'task', 'attachment',
            ]), $request->user());

            return response()->json([
                'ok' => true,
                'message' => $payload,
            ]);
        }

        Toast::success('Отправлено');

        return redirect()->route('platform.systems.chats.view', $chat);
    }

    public function toggleMute(Request $request, Chat $chat, ChatService $chats)
    {
        $muted = $chats->toggleMute($chat, $request->user());
        Toast::info($muted ? 'Чат без звука' : 'Уведомления включены');

        return redirect()->route('platform.systems.chats.view', $chat);
    }

    public function togglePin(Request $request, Chat $chat, ChatService $chats)
    {
        $pinned = $chats->togglePin($chat, $request->user());
        Toast::info($pinned ? 'Чат закреплён' : 'Чат откреплён');

        return redirect()->route('platform.systems.chats.view', $chat);
    }

    public function saveChat(Request $request, Chat $chat, ChatService $chats)
    {
        $data = $request->validate([
            'chat.title' => 'required|string|max:120',
            'chat.description' => 'nullable|string|max:1000',
            'chat.avatar_path' => 'nullable|string|max:500',
        ]);

        $chats->updateChat($chat, $request->user(), $data['chat']);
        Toast::success('Чат обновлён');

        return redirect()->route('platform.systems.chats.view', $chat);
    }

    public function removeChat(Request $request, Chat $chat, ChatService $chats)
    {
        $chats->deleteGroup($chat, $request->user());
        Toast::success('Чат удалён');

        return redirect()->route('platform.systems.chats');
    }
}
