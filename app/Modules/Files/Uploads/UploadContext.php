<?php

declare(strict_types=1);

namespace App\Modules\Files\Uploads;

/**
 * What an upload was made for, when something other than core decides
 * that: a key naming the handler that owns it (UploadContextHandlers) and
 * an id meaning whatever that handler says it means. Core stores the pair
 * on the session and hands it back on FileWasStored without reading it.
 */
final readonly class UploadContext
{
    public function __construct(
        public string $key,
        public string $id,
    ) {}
}
