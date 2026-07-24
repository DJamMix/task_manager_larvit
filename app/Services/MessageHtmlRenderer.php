<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Единый рендер сообщений (обсуждение задач + чаты).
 * Код: тёмные блоки, ```fence```, Quill code-block / <pre>.
 */
final class MessageHtmlRenderer
{
    public function render(mixed $payload, ?string $plainText = null): string
    {
        if (is_string($payload) && $payload !== '') {
            if ($this->looksLikeHtml($payload)) {
                return $this->enhance($this->sanitizeHtml($payload));
            }

            return $this->enhance($this->plainToHtml($payload));
        }

        if (!is_array($payload) || $payload === []) {
            return $this->enhance($this->plainToHtml((string) ($plainText ?? '')));
        }

        if (!empty($payload['html']) && is_string($payload['html'])) {
            return $this->enhance($this->sanitizeHtml($payload['html']));
        }

        if (!empty($payload['ops']) && is_array($payload['ops'])) {
            $html = $this->deltaToHtml($payload['ops']);

            return $this->enhance($html !== '' ? $html : $this->plainToHtml((string) ($plainText ?? '')));
        }

        return $this->enhance($this->plainToHtml((string) ($plainText ?? '')));
    }

    /**
     * @param  list<array<string, mixed>>  $ops
     */
    private function deltaToHtml(array $ops): string
    {
        $lines = [['html' => '', 'code' => false, 'lang' => '']];

        foreach ($ops as $op) {
            $insert = $op['insert'] ?? null;
            if (!is_string($insert)) {
                continue;
            }

            if ($this->looksLikeHtml($insert) && preg_match('/<(pre|p|div|ul|ol)\b/i', $insert)) {
                return $this->sanitizeHtml($insert);
            }

            $attrs = is_array($op['attributes'] ?? null) ? $op['attributes'] : [];
            $parts = explode("\n", $insert);
            $last = count($parts) - 1;

            foreach ($parts as $i => $part) {
                $lines[array_key_last($lines)]['html'] .= $this->formatInline($part, $attrs);

                if ($i < $last) {
                    $codeAttr = $attrs['code-block'] ?? false;
                    $lines[array_key_last($lines)]['code'] = (bool) $codeAttr;
                    $lines[array_key_last($lines)]['lang'] = is_string($codeAttr) ? $codeAttr : '';
                    $lines[] = ['html' => '', 'code' => false, 'lang' => ''];
                }
            }
        }

        $out = '';
        $codeChunk = [];
        $lang = '';

        $flushCode = function () use (&$out, &$codeChunk, &$lang) {
            if ($codeChunk === []) {
                return;
            }
            $out .= $this->renderCodeBlock(implode("\n", $codeChunk), $lang);
            $codeChunk = [];
            $lang = '';
        };

        foreach ($lines as $line) {
            if ($line['code']) {
                $codeChunk[] = html_entity_decode(strip_tags($line['html']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($line['lang'] !== '') {
                    $lang = $line['lang'];
                }
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

        if ($attrs['code'] ?? false) {
            return '<code class="tw-inline-code">' . $html . '</code>';
        }

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
        if (isset($attrs['color'])) {
            $style .= 'color:' . e((string) $attrs['color']) . ';';
        }
        if (isset($attrs['link'])) {
            $url = e((string) $attrs['link']);

            return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $html . '</a>';
        }

        return $style !== '' ? '<span style="' . $style . '">' . $html . '</span>' : $html;
    }

    private function plainToHtml(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        // Markdown fences ```lang\n...\n```
        $parts = preg_split('/```([a-zA-Z0-9_-]*)\s*\n([\s\S]*?)```/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false || count($parts) === 1) {
            return nl2br(e($text));
        }

        $html = '';
        for ($i = 0; $i < count($parts); $i++) {
            if ($i % 3 === 0) {
                $chunk = $parts[$i];
                if ($chunk !== '') {
                    $html .= nl2br(e($chunk));
                }
            } elseif ($i % 3 === 1) {
                $lang = $parts[$i];
                $code = $parts[$i + 1] ?? '';
                $html .= $this->renderCodeBlock(rtrim($code, "\n"), $lang);
                $i++;
            }
        }

        return $html;
    }

    public function renderCodeBlock(string $code, string $lang = ''): string
    {
        $lang = preg_replace('/[^a-zA-Z0-9_-]/', '', $lang) ?? '';
        $langClass = $lang !== '' ? ' language-' . $lang : '';
        $label = $lang !== '' ? strtoupper($lang) : 'CODE';

        return '<div class="tw-codeblock" data-lang="' . e($lang) . '">'
            . '<div class="tw-codeblock__bar">'
            . '<span>' . e($label) . '</span>'
            . '<button type="button" class="tw-code-copy">Копировать</button>'
            . '</div>'
            . '<pre><code class="hljs' . $langClass . '">' . e($code) . '</code></pre>'
            . '</div>';
    }

    private function enhance(string $html): string
    {
        $html = preg_replace_callback(
            '/<pre[^>]*>([\s\S]*?)<\/pre>/i',
            function (array $m) {
                if (str_contains($m[0], 'tw-codeblock')) {
                    return $m[0];
                }
                $inner = html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $lang = '';
                if (preg_match('/class=["\'][^"\']*language-([a-zA-Z0-9_-]+)/', $m[0], $lm)) {
                    $lang = $lm[1];
                }

                return $this->renderCodeBlock($inner, $lang);
            },
            $html
        ) ?? $html;

        // Автоссылки на задачи /admin/.../tasks/...
        $html = preg_replace_callback(
            '/(?<!["\'>])(https?:\/\/[^\s<]+|(?:\/admin\/)?(?:tasks\/\d+(?:\/edit)?|my-tasks\/\d+|client\/projects\/\d+\/tasks\/\d+))/i',
            function (array $m) {
                $url = $m[1];
                if (!str_starts_with($url, 'http') && !str_starts_with($url, '/')) {
                    return e($url);
                }
                $href = e($url);
                $label = e(\Illuminate\Support\Str::limit($url, 60));

                return '<a class="tw-msg-link" href="' . $href . '" target="_blank" rel="noopener">' . $label . '</a>';
            },
            $html
        ) ?? $html;

        return $html;
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

        return preg_replace('/javascript:/i', '', $clean) ?? $clean;
    }
}
