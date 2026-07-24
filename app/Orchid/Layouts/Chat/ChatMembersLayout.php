<?php

namespace App\Orchid\Layouts\Chat;

use App\Services\ChatService;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Layouts\Rows;

class ChatMembersLayout extends Rows
{
    protected $title;

    protected function fields(): iterable
    {
        $options = app(ChatService::class)->chatMemberOptions();
        $chat = $this->query->get('chat');
        $current = $chat?->members?->pluck('id')->all() ?? [];

        return [
            Select::make('chat.member_ids.')
                ->options($options)
                ->multiple()
                ->title('Участники чата')
                ->value($current)
                ->help('Сотрудники и контакты клиентов. Владелец остаётся в чате.'),
        ];
    }
}
