<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Orchid\Attachment\Attachable;
use Orchid\Screen\AsSource;

class Comment extends Model
{
    use HasFactory, AsSource, Attachable;

    protected $fillable = [
        'user_id',
        'task_id',
        'parent_id',
        'text',
        'plain_text',
        'is_system',
        'mentioned_user_ids',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'text' => 'array',
        'is_system' => 'boolean',
        'mentioned_user_ids' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function getFormattedTextAttribute(): string
    {
        $payload = $this->text;

        // Quill / legacy: иногда в text лежит HTML-строка
        if (is_string($payload) && $payload !== '') {
            return $this->sanitizeHtml($payload);
        }

        if (!is_array($payload) || $payload === []) {
            return nl2br(e(strip_tags((string) ($this->plain_text ?? ''))));
        }

        if (!empty($payload['html']) && is_string($payload['html'])) {
            return $this->sanitizeHtml($payload['html']);
        }

        if (empty($payload['ops']) || !is_array($payload['ops'])) {
            return nl2br(e(strip_tags((string) ($this->plain_text ?? ''))));
        }

        $html = '';
        foreach ($payload['ops'] as $op) {
            $insert = $op['insert'] ?? null;

            if ($insert === "\n") {
                $html .= '<br>';
                continue;
            }

            if (!is_string($insert)) {
                continue;
            }

            // Старые записи: в insert лежит целый <p>...</p>
            if ($this->looksLikeHtml($insert)) {
                $html .= $this->sanitizeHtml($insert);
                continue;
            }

            $text = e($insert);
            $attrs = $op['attributes'] ?? [];
            $style = '';

            if ($attrs['bold'] ?? false) {
                $style .= 'font-weight:600;';
            }
            if ($attrs['italic'] ?? false) {
                $style .= 'font-style:italic;';
            }
            if ($attrs['underline'] ?? false) {
                $style .= 'text-decoration:underline;';
            }
            if (isset($attrs['color'])) {
                $style .= 'color:' . e((string) $attrs['color']) . ';';
            }

            $html .= $style !== '' ? '<span style="' . $style . '">' . $text . '</span>' : $text;
        }

        return $html !== ''
            ? $html
            : nl2br(e(strip_tags((string) ($this->plain_text ?? ''))));
    }

    private function looksLikeHtml(string $value): bool
    {
        return (bool) preg_match('/<\/?[a-z][\s\S]*>/i', $value);
    }

    private function sanitizeHtml(string $html): string
    {
        $allowed = '<p><br><br/><b><strong><i><em><u><ul><ol><li><a><span><h1><h2><h3><blockquote><code><pre>';
        $clean = strip_tags($html, $allowed);
        $clean = preg_replace('/\son\w+="[^"]*"/i', '', $clean) ?? $clean;
        $clean = preg_replace("/\son\w+='[^']*'/i", '', $clean) ?? $clean;
        $clean = preg_replace('/javascript:/i', '', $clean) ?? $clean;

        // Пустые обёртки Quill
        if (trim(strip_tags($clean)) === '') {
            return '';
        }

        return $clean;
    }
}
