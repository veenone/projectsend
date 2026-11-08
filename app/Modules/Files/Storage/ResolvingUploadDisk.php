<?php

declare(strict_types=1);

namespace App\Modules\Files\Storage;

use App\Models\User;
use App\Modules\Files\Uploads\UploadContext;

/**
 * Dispatched once per upload, before bytes are written, to decide which
 * Laravel disk they should land on. Defaults to the local 'files' disk —
 * a fresh or Cloud install has nothing listening, so behavior is
 * unchanged from before this event existed.
 *
 * A listener (see ExternalStorageConfigApplier) sets $disk to
 * a bucket (UploadLocations) when the community-only storage settings are
 * active. A listener that must decide the disk outright, whatever runs
 * after it, claims it instead: the first claim wins, and every caller
 * writes to $claimedDisk ?? $disk. Deliberately a plain event with a
 * mutable property, not an interface a listener must implement — core never references any
 * specific feature's class this way, so nothing here breaks if no
 * listener is registered at all (see docs/extension-points-architecture.md).
 */
class ResolvingUploadDisk
{
    public string $disk = 'files';

    public ?string $claimedDisk = null;

    public function __construct(
        public readonly User $uploader,
        // The location a client picked for this upload, when the upload
        // path lets them pick one (UploadSession::storage_location_id).
        // A hint: UploadLocations only honors it if it is theirs to use.
        public readonly ?int $requestedLocationId = null,
        // The context the upload was started in, if any. Its handler's
        // disk, when it names one, arrives already claimed.
        public readonly ?UploadContext $context = null,
        // The folder the file will land in; null for the library root.
        public readonly ?int $folderId = null,
    ) {}

    public function claim(string $disk): void
    {
        $this->claimedDisk ??= $disk;
    }
}
