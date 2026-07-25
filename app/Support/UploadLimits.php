<?php

namespace App\Support;

use Orchid\Support\Init;

/**
 * Лимиты Upload-полей Orchid не должны превышать upload_max_filesize / post_max_size из PHP.ini,
 * иначе RuntimeException на отрисовке экрана.
 */
final class UploadLimits
{
    /**
     * Максимальный размер файла в МБ для Upload::maxFileSize().
     */
    public static function maxMb(float $desired = 50): float
    {
        $server = (float) Init::maxFileUpload(Init::MB);
        if ($server <= 0) {
            return 1.0;
        }

        return max(0.1, min($desired, $server));
    }
}
