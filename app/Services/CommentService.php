<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use App\CoreLayer\Integrations\Ebot\EBot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class CommentService
{
    public function __construct(
        private readonly EBot $ebot,
    ) {}

    public function addFromRequest(Task $task, User $actor, Request $request): Comment
    {
        $quillContent = $this->normalizeQuill($request->input('comment.text'));
        $plainText = $this->extractPlainText($quillContent);

        if (trim($plainText) === '' && empty($request->input('comment.attachments'))) {
            abort(422, 'Напишите сообщение или прикрепите файл');
        }

        $parentId = $request->input('comment.parent_id');
        $parent = null;
        if ($parentId) {
            $parent = Comment::query()
                ->where('task_id', $task->id)
                ->whereKey($parentId)
                ->first();
        }

        $mentionIds = collect($request->input('comment.notify_user_ids', []))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        // Ответ автоматически пингует автора исходного сообщения
        if ($parent && $parent->user_id && (int) $parent->user_id !== (int) $actor->id) {
            $mentionIds->push((int) $parent->user_id);
        }

        $mentionIds = $mentionIds->unique()->reject(fn ($id) => $id === (int) $actor->id)->values();

        $comment = $task->comments()->create([
            'user_id' => $actor->id,
            'parent_id' => $parent?->id,
            'text' => $quillContent,
            'plain_text' => $plainText !== '' ? $plainText : '📎 Вложение',
            'is_system' => false,
            'mentioned_user_ids' => $mentionIds->all(),
        ]);

        $attachmentIds = $request->input('comment.attachments', []);
        if (!empty($attachmentIds)) {
            $comment->attachment()->syncWithoutDetaching($attachmentIds);
        }

        $this->notifyAboutComment($task, $actor, $comment, $parent, $mentionIds->all());

        return $comment;
    }

    public function notifyAboutComment(
        Task $task,
        User $actor,
        Comment $comment,
        ?Comment $parent,
        array $mentionIds
    ): void {
        $preview = \Illuminate\Support\Str::limit($comment->plain_text, 180);
        $base = "💬 {$actor->displayName()} написал(а) в задаче «{$task->name}»:\n{$preview}";

        if ($parent) {
            $parentAuthor = $parent->user?->displayName() ?? 'участник';
            $base = "↩️ {$actor->displayName()} ответил(а) {$parentAuthor} в задаче «{$task->name}»:\n{$preview}";
        }

        $recipients = collect();

        // Явные упоминания / ответ
        foreach ($mentionIds as $userId) {
            $recipients->push((int) $userId);
        }

        // Если никого не указали явно — уведомляем ключевых участников
        if ($recipients->isEmpty()) {
            if ($task->executor_id) {
                $recipients->push((int) $task->executor_id);
            }
            if ($task->creator_id) {
                $recipients->push((int) $task->creator_id);
            }
            foreach ($task->observerIds() as $observerId) {
                $recipients->push((int) $observerId);
            }
            foreach ($task->project?->clients ?? [] as $client) {
                $recipients->push((int) $client->id);
            }
        }

        $recipients = $recipients
            ->unique()
            ->reject(fn ($id) => $id === (int) $actor->id)
            ->values();

        $users = User::query()->whereIn('id', $recipients)->get()->keyBy('id');

        foreach ($recipients as $userId) {
            $user = $users->get($userId);
            if (!$user?->telegram_id) {
                continue;
            }

            $url = $this->taskUrlFor($user, $task);
            $roleNote = $this->roleNoteFor($user, $task, $mentionIds, $parent);
            $message = $base . "\n\n🔗 [Открыть задачу]({$url})" . ($roleNote ? "\n{$roleNote}" : '');

            try {
                $this->ebot->sendMessage($user->telegram_id, $message, null, 'Markdown');
            } catch (\Throwable) {
                // Не ломаем отправку комментария из‑за Telegram
            }
        }
    }

    private function taskUrlFor(User $user, Task $task): string
    {
        if ($user->hasAccess('platform.systems.my_tasks')) {
            return URL::route('platform.systems.my_tasks.view', $task->id);
        }

        if ($user->hasAccess('platform.systems.client.project.tasks.view') && $task->project_id) {
            return URL::route('platform.systems.client.project.tasks.view', [
                'project' => $task->project_id,
                'task' => $task->id,
            ]);
        }

        if ($user->hasAccess('platform.systems.tasks')) {
            return URL::route('platform.systems.tasks.edit', $task->id);
        }

        return URL::route('platform.welcome');
    }

    private function roleNoteFor(User $user, Task $task, array $mentionIds, ?Comment $parent): string
    {
        if (in_array((int) $user->id, $mentionIds, true)) {
            if ($parent && (int) $parent->user_id === (int) $user->id) {
                return 'ℹ️ Вам ответили в обсуждении';
            }

            return 'ℹ️ Вас упомянули в обсуждении';
        }

        if ((int) $task->executor_id === (int) $user->id) {
            return 'ℹ️ Вы исполнитель задачи';
        }

        if ($task->isObserver((int) $user->id)) {
            return 'ℹ️ Вы наблюдатель задачи';
        }

        return 'ℹ️ Вы участник задачи';
    }

    public function normalizeQuill(mixed $quillData): array
    {
        if (is_array($quillData)) {
            if (!empty($quillData['html']) && is_string($quillData['html'])) {
                return $this->fromHtml($quillData['html']);
            }

            if (!empty($quillData['ops']) && is_array($quillData['ops'])) {
                return $this->sanitizeDelta($quillData);
            }

            return $quillData;
        }

        if (is_string($quillData) && json_validate($quillData)) {
            $decoded = json_decode($quillData, true);
            if (is_array($decoded)) {
                return $this->normalizeQuill($decoded);
            }
        }

        $raw = (string) ($quillData ?? '');

        if ($this->looksLikeHtml($raw)) {
            return $this->fromHtml($raw);
        }

        return [
            'ops' => [
                ['insert' => $raw === '' || str_ends_with($raw, "\n") ? $raw : $raw . "\n"],
            ],
        ];
    }

    public function extractPlainText(array $quillContent): string
    {
        if (!empty($quillContent['html']) && is_string($quillContent['html'])) {
            return $this->htmlToPlain($quillContent['html']);
        }

        $plainText = '';
        foreach ($quillContent['ops'] ?? [] as $op) {
            if (!is_string($op['insert'] ?? null)) {
                continue;
            }

            $insert = $op['insert'];
            $plainText .= $this->looksLikeHtml($insert)
                ? $this->htmlToPlain($insert)
                : $insert;
        }

        return trim(preg_replace('/\s+/', ' ', $plainText) ?? '');
    }

    private function fromHtml(string $html): array
    {
        $safe = $this->sanitizeHtml($html);

        return [
            'html' => $safe,
            'ops' => [
                ['insert' => $this->htmlToPlain($safe) . "\n"],
            ],
        ];
    }

    private function sanitizeDelta(array $delta): array
    {
        $ops = [];
        foreach ($delta['ops'] ?? [] as $op) {
            if (!is_array($op)) {
                continue;
            }

            $insert = $op['insert'] ?? null;
            if (is_string($insert) && $this->looksLikeHtml($insert)) {
                return $this->fromHtml($insert);
            }

            $ops[] = $op;
        }

        $delta['ops'] = $ops;

        return $delta;
    }

    private function looksLikeHtml(string $value): bool
    {
        return (bool) preg_match('/<\/?[a-z][\s\S]*>/i', $value);
    }

    private function htmlToPlain(string $html): string
    {
        $text = str_ireplace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</li>'], "\n", $html);
        $text = strip_tags($text);

        return trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function sanitizeHtml(string $html): string
    {
        $allowed = '<p><br><br/><b><strong><i><em><u><ul><ol><li><a><span><h1><h2><h3><blockquote><code><pre>';
        $clean = strip_tags($html, $allowed);
        $clean = preg_replace('/\son\w+="[^"]*"/i', '', $clean) ?? $clean;
        $clean = preg_replace("/\son\w+='[^']*'/i", '', $clean) ?? $clean;

        return preg_replace('/javascript:/i', '', $clean) ?? $clean;
    }
}
