<?php

declare(strict_types=1);

namespace App\Modules\Files\Uploads;

use App\Models\User;
use App\Modules\Files\Models\Folder;

/**
 * The owner of an upload context, registered under its key in
 * UploadContextHandlers.
 */
interface UploadContextHandler
{
    /**
     * Whether $uploader may start an upload in this context into $folder,
     * the folder the file will land in (null for the library root). Asked
     * when the session is created, before any byte is sent; false is a 422.
     */
    public function authorize(User $uploader, string $id, ?Folder $folder): bool;

    /**
     * The disk the bytes should land on, or null to leave the choice to
     * the uploader's storage locations as for any other upload.
     *
     * Used as-is: it bypasses UploadLocations::usableFor(), so it must
     * name a disk that is configured, and one this uploader may write to.
     */
    public function disk(User $uploader, string $id): ?string;
}
