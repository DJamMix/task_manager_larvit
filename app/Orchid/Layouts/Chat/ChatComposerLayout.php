<?php

namespace App\Orchid\Layouts\Chat;

use App\Models\Task;
use App\Services\ChatService;
use App\Support\UploadLimits;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Group;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Quill;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\Upload;
use Orchid\Screen\Layouts\Rows;

class ChatComposerLayout extends Rows
{
    protected $title;

    protected function fields(): iterable
    {
        $chat = $this->query->get('chat');
        $members = $chat?->members
            ?->reject(fn ($u) => (int) $u->id === (int) auth()->id())
            ->mapWithKeys(fn ($u) => [$u->id => $u->displayName()])
            ->all() ?? [];

        $tasks = Task::query()
            ->orderByDesc('id')
            ->limit(80)
            ->get()
            ->mapWithKeys(fn (Task $t) => [$t->id => "#{$t->id} · {$t->name}"])
            ->all();

        return [
            Input::make('message.parent_id')
                ->type('hidden')
                ->id('chat-message-parent-id'),

            Quill::make('message.text')
                ->toolbar(['text', 'list', 'quote', 'format'])
                ->height('100px')
                ->placeholder('Сообщение… Код — </> или ```php'),

            Group::make([
                Select::make('message.task_id')
                    ->options($tasks)
                    ->empty('Прикрепить задачу')
                    ->title('Задача'),

                Select::make('message.notify_user_ids.')
                    ->options($members)
                    ->multiple()
                    ->title('Уведомить')
                    ->empty('Авто'),
            ])->fullWidth(),

            Upload::make('message.attachments')
                ->title('Файлы')
                ->acceptedFiles('image/*,application/pdf,.zip,.rar,.doc,.docx,.xls,.xlsx,.txt,.php,.js,.ts,.json,.sql,.css')
                ->storage('public')
                ->maxFileSize(UploadLimits::maxMb(256))
                ->maxFiles(5),

            Button::make('Отправить')
                ->method('sendMessage')
                ->icon('bs.send')
                ->class('btn btn-primary btn-sm'),
        ];
    }
}
