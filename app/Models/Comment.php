<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'task_id',
        'text',
        'plain_text'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'text' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function getFormattedTextAttribute()
    {
        if (empty($this->text)) {
            return nl2br(e($this->plain_text ?? ''));
        }

        $html = '';
        foreach ($this->text['ops'] ?? [] as $op) {
            if ($op['insert'] === "\n") {
                $html .= "<br>";
                continue;
            }

            if (is_string($op['insert'] ?? null)) {
                $text = htmlspecialchars($op['insert']);
                $attrs = $op['attributes'] ?? [];

                $style = '';
                if (!empty($attrs)) {
                    if ($attrs['bold'] ?? false) $style .= 'font-weight:bold;';
                    if ($attrs['italic'] ?? false) $style .= 'font-style:italic;';
                    if (isset($attrs['color'])) $style .= 'color:' . $attrs['color'] . ';';
                }

                $html .= $style ? "<span style=\"$style\">$text</span>" : $text;
            }
        }

        return $html;
    }

    protected function convertDeltaToHtml(array $ops): string
    {
        $html = '';
        foreach ($ops as $op) {
            if ($op['insert'] === "\n") {
                $html .= "<br>";
                continue;
            }

            $text = htmlspecialchars($op['insert']);
            $attrs = $op['attributes'] ?? [];

            if (!empty($attrs)) {
                $styles = [];
                if (isset($attrs['bold'])) $styles[] = 'font-weight:bold';
                if (isset($attrs['italic'])) $styles[] = 'font-style:italic';
                if (isset($attrs['color'])) $styles[] = 'color:' . $attrs['color'];
                
                $html .= sprintf(
                    '<span style="%s">%s</span>', 
                    implode(';', $styles), 
                    $text
                );
            } else {
                $html .= $text;
            }
        }
        
        return $html;
    }
}
