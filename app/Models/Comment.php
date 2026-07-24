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
        if (empty($this->text) || empty($this->text['ops'] ?? null)) {
            return nl2br(e($this->plain_text ?? ''));
        }

        // Если TaskLogger положил готовый html
        if (!empty($this->text['html'])) {
            return $this->text['html'];
        }

        $html = '';
        foreach ($this->text['ops'] as $op) {
            if (($op['insert'] ?? null) === "\n") {
                $html .= '<br>';
                continue;
            }

            if (!is_string($op['insert'] ?? null)) {
                continue;
            }

            $text = e($op['insert']);
            $attrs = $op['attributes'] ?? [];
            $style = '';

            if ($attrs['bold'] ?? false) {
                $style .= 'font-weight:600;';
            }
            if ($attrs['italic'] ?? false) {
                $style .= 'font-style:italic;';
            }
            if (isset($attrs['color'])) {
                $style .= 'color:' . e($attrs['color']) . ';';
            }

            $html .= $style !== '' ? '<span style="' . $style . '">' . $text . '</span>' : $text;
        }

        return $html !== '' ? $html : nl2br(e($this->plain_text ?? ''));
    }
}
