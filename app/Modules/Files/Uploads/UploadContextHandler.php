<?php

declare(strict_types=1);

namespace App\Modules\Files\Uploads;

use App\Models\User;

/**
 * The owner of an upload context, registered under its key in
 * UploadContextHandlers.
 */
interface UploadContextHandler
{
    /**
     * Whether $uploader may start an upload in this context. Asked when
     * the session is created, before any byte is sent; false is a 422.
     */
    public function authorize(User $uploader, string $id): bool;

    /**
     * The disk the bytes should land on, or null to leave the choice to
     * the uploader's storage locations as for any other upload.
     */
    public function disk(User $uploader, string $id): ?string;
}
