<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Orchid\Attachment\Models\Attachment;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttachmentController extends Controller
{
    public function show(Request $request, Attachment $attachment): BinaryFileResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        // Authenticated mobile users can fetch attachments by id (same as staff in panel).
        $path = storage_path('app/public/' . $attachment->physicalPath());

        if (!is_file($path)) {
            abort(404, 'Файл не найден');
        }

        $mime = strtolower((string) ($attachment->mime ?? 'application/octet-stream'));
        $ext = strtolower((string) ($attachment->extension ?? ''));
        $group = strtolower((string) ($attachment->group ?? ''));
        $isVoice = $group === 'voice' || str_starts_with($mime, 'audio/');

        if ($isVoice) {
            $mime = match (true) {
                $ext === 'wav' || str_contains($mime, 'wav') => 'audio/wav',
                $ext === 'mp3' => 'audio/mpeg',
                in_array($ext, ['ogg', 'oga', 'opus'], true) => 'audio/ogg',
                in_array($ext, ['m4a', 'mp4', 'aac'], true) => 'audio/mp4',
                $ext === 'webm' => 'audio/webm',
                default => (str_starts_with($mime, 'audio/') ? $mime : 'audio/webm'),
            };
        }

        $inline = $request->boolean('inline')
            || $isVoice
            || str_starts_with($mime, 'image/')
            || str_starts_with($mime, 'audio/');

        if ($inline) {
            return response()->file($path, [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="' . addslashes((string) $attachment->original_name) . '"',
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'private, max-age=3600',
            ]);
        }

        return response()->download($path, (string) $attachment->original_name);
    }
}
