<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Форматирование текста бота (Telegram-like parse_mode).
 */
final class BotMessageFormatter
{
    public function format(string $text, ?string $parseMode = null): array
    {
        $parseMode = strtoupper(trim((string) $parseMode));
        $plain = trim($text);

        if ($plain === '') {
            return [
                'plain' => '',
                'quill' => ['ops' => [['insert' => "\n"]], 'html' => ''],
            ];
        }

        $html = match ($parseMode) {
            'HTML' => $this->fromHtml($plain),
            'MARKDOWN', 'MARKDOWNV2' => $this->fromMarkdown($plain),
            default => $this->fromPlain($plain),
        };

        return [
            'plain' => trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            'quill' => [
                'html' => $html,
                'ops' => [['insert' => $plain."\n"]],
                'parse_mode' => $parseMode !== '' ? $parseMode : null,
            ],
        ];
    }

    private function fromPlain(string $text): string
    {
        $escaped = e($text);
        $escaped = preg_replace('/\n{2,}/', "</p><p>", $escaped) ?? $escaped;
        $escaped = nl2br($escaped, false);

        return '<p>'.$escaped.'</p>';
    }

    private function fromHtml(string $html): string
    {
        $allowed = '<b><strong><i><em><u><ins><s><strike><del><a><code><pre><br><p><ul><ol><li><blockquote><span>';
        $safe = strip_tags($html, $allowed);
        $safe = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $safe) ?? $safe;
        $safe = preg_replace('/javascript\s*:/i', '', $safe) ?? $safe;

        if (! preg_match('/<(p|div|pre|ul|ol|br)\b/i', $safe)) {
            $safe = nl2br($safe, false);
            $safe = '<p>'.$safe.'</p>';
        }

        return $safe;
    }

    private function fromMarkdown(string $text): string
    {
        $html = e($text);

        // ```code```
        $html = preg_replace_callback('/```(\w+)?\n?([\s\S]*?)```/', function ($m) {
            $code = $m[2] ?? '';

            return '</p><pre class="bx-bot-pre"><code>'.$code.'</code></pre><p>';
        }, $html) ?? $html;

        $html = preg_replace('/`([^`\n]+)`/', '<code>$1</code>', $html) ?? $html;
        $html = preg_replace('/\*\*([^*\n]+)\*\*/', '<strong>$1</strong>', $html) ?? $html;
        $html = preg_replace('/\*([^*\n]+)\*/', '<em>$1</em>', $html) ?? $html;
        $html = preg_replace('/_([^_\n]+)_/', '<em>$1</em>', $html) ?? $html;
        $html = preg_replace('/~~([^~\n]+)~~/', '<s>$1</s>', $html) ?? $html;
        $html = preg_replace('/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/', '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>', $html) ?? $html;
        $html = preg_replace('/\n{2,}/', '</p><p>', $html) ?? $html;
        $html = nl2br($html, false);

        return '<div class="bx-bot-rich"><p>'.$html.'</p></div>';
    }
}
