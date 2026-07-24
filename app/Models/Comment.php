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

        if (is_string($payload) && $payload !== '') {
            return $this->enhanceCodeBlocks($this->sanitizeHtml($payload));
        }

        if (!is_array($payload) || $payload === []) {
            return nl2br(e(strip_tags((string) ($this->plain_text ?? ''))));
        }

        if (!empty($payload['html']) && is_string($payload['html'])) {
            return $this->enhanceCodeBlocks($this->sanitizeHtml($payload['html']));
        }

        if (empty($payload['ops']) || !is_array($payload['ops'])) {
            return nl2br(e(strip_tags((string) ($this->plain_text ?? ''))));
        }

        $html = $this->deltaToHtml($payload['ops']);

        return $html !== ''
            ? $html
            : nl2br(e(strip_tags((string) ($this->plain_text ?? ''))));
    }

    /**
     * @param  list<array<string, mixed>>  $ops
     */
    private function deltaToHtml(array $ops): string
    {
        /** @var list<array{html: string, code: bool}> $lines */
        $lines = [['html' => '', 'code' => false]];

        foreach ($ops as $op) {
            $insert = $op['insert'] ?? null;
            if (!is_string($insert)) {
                continue;
            }

            // Старые записи: HTML целиком в insert
            if ($this->looksLikeHtml($insert) && !str_contains($insert, "\n")) {
                $lines[array_key_last($lines)]['html'] .= $this->sanitizeHtml($insert);
                continue;
            }

            if ($this->looksLikeHtml($insert) && preg_match('/<(pre|p|div|ul|ol)\b/i', $insert)) {
                return $this->enhanceCodeBlocks($this->sanitizeHtml($insert));
            }

            $attrs = is_array($op['attributes'] ?? null) ? $op['attributes'] : [];
            $parts = explode("\n", $insert);
            $lastIndex = count($parts) - 1;

            foreach ($parts as $i => $part) {
                $lines[array_key_last($lines)]['html'] .= $this->formatInline($part, $attrs);

                if ($i < $lastIndex) {
                    $lines[array_key_last($lines)]['code'] = !empty($attrs['code-block']);
                    $lines[] = ['html' => '', 'code' => false];
                }
            }
        }

        $out = '';
        $codeChunk = [];

        $flushCode = function () use (&$out, &$codeChunk) {
            if ($codeChunk === []) {
                return;
            }
            $code = implode("\n", $codeChunk);
            $out .= $this->renderCodeBlock($code);
            $codeChunk = [];
        };

        foreach ($lines as $line) {
            if ($line['code']) {
                // В code-block храним сырой текст без HTML-обёрток
                $codeChunk[] = html_entity_decode(strip_tags($line['html']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                continue;
            }

            $flushCode();
            $out .= ($line['html'] !== '' ? $line['html'] : '') . '<br>';
        }

        $flushCode();

        return preg_replace('/(<br>)+$/', '', $out) ?? $out;
    }

    private function formatInline(string $text, array $attrs): string
    {
        if ($text === '') {
            return '';
        }

        $html = e($text);
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
        if ($attrs['strike'] ?? false) {
            $style .= 'text-decoration:line-through;';
        }
        if (!empty($attrs['code'])) {
            return '<code class="tw-inline-code">' . $html . '</code>';
        }
        if (isset($attrs['color'])) {
            $style .= 'color:' . e((string) $attrs['color']) . ';';
        }

        return $style !== '' ? '<span style="' . $style . '">' . $html . '</span>' : $html;
    }

    private function renderCodeBlock(string $code): string
    {
        return '<div class="tw-codeblock">'
            . '<div class="tw-codeblock__bar">'
            . '<span>Код</span>'
            . '<button type="button" class="tw-code-copy">Копировать</button>'
            . '</div>'
            . '<pre><code>' . e($code) . '</code></pre>'
            . '</div>';
    }

    private function enhanceCodeBlocks(string $html): string
    {
        return preg_replace_callback(
            '/<pre[^>]*>([\s\S]*?)<\/pre>/i',
            function (array $m) {
                $inner = html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return $this->renderCodeBlock($inner);
            },
            $html
        ) ?? $html;
    }

    private function looksLikeHtml(string $value): bool
    {
        return (bool) preg_match('/<\/?[a-z][\s\S]*>/i', $value);
    }

    private function sanitizeHtml(string $html): string
    {
        $allowed = '<p><br><br/><b><strong><i><em><u><s><ul><ol><li><a><span><h1><h2><h3><blockquote><code><pre>';
        $clean = strip_tags($html, $allowed);
        $clean = preg_replace('/\son\w+="[^"]*"/i', '', $clean) ?? $clean;
        $clean = preg_replace("/\son\w+='[^']*'/i", '', $clean) ?? $clean;
        $clean = preg_replace('/javascript:/i', '', $clean) ?? $clean;

        if (trim(strip_tags($clean)) === '') {
            return '';
        }

        return $clean;
    }
}
