<?php

namespace App\Orchid\Layouts\Client;

use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class ClientTaskFilesLayout extends Table
{
    /**
     * Data source.
     *
     * The name of the key to fetch it from the query.
     * The results of which will be elements of the table.
     *
     * @var string
     */
    protected $target = 'task.attachments';

    /**
     * Get the table cells to be displayed.
     *
     * @return TD[]
     */
    protected function columns(): array
    {
        return [
            TD::make('original_name', 'Имя файла'),
            TD::make('size', 'Размер')
                ->render(function ($attachment) {
                    return formatBytes($attachment->size);
                }),
            TD::make('created_at', 'Дата загрузки')
                ->render(function ($attachment) {
                    return $attachment->created_at->format('d.m.Y H:i');
                }),
            TD::make('action', '')
                ->render(function ($attachment) {
                    return Link::make('Скачать')
                        ->href(route('platform.task.attachment.download', $attachment))
                        ->icon('cloud-download');
                }),
        ];
    }
}
