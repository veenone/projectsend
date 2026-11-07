<?php

declare(strict_types=1);

namespace App\Modules\Files\Storage;

use App\Models\User;
use App\Modules\Platform\Settings\ExternalStorageConfigApplier;
use App\Modules\Platform\Settings\StorageLocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Where a new upload goes.
 *
 * Someone with storage locations of their own, or a client whose groups
 * have some, uploads to the first of them that can take it, or to another
 * they picked for this upload. Everyone else uploads to the default
 * location. A location only counts while it accepts uploads and, for a
 * bucket, while it is configured and this edition permits buckets at all.
 */
class UploadLocations
{
    public function __construct(
        private readonly ExternalStorageConfigApplier $storage,
    ) {}

    /**
     * The locations this person may upload to, the preferred one first:
     * their own list in its saved order, then the storages of their groups
     * (clients only; default first, then by name), each listed once. Empty
     * when their uploads follow the default.
     *
     * @return Collection<int, StorageLocation>
     */
    public function usableFor(User $uploader): Collection
    {
        // Boot runs before `artisan migrate`, so on an upgrade this can be
        // asked while the tables are not there yet: everyone follows the
        // default until they are.
        if (! $this->storage->permitted() || ! Schema::hasTable('storage_locations')) {
            return collect();
        }

        $buckets = array_column($this->storage->externalLocations(), 'disk');

        $fromGroups = $uploader->isClient()
            ? StorageLocation::query()
                ->whereHas('groups.members', fn ($members) => $members->whereKey($uploader->id))
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get()
            : collect();

        return $uploader->storageLocations()->get()
            ->concat($fromGroups)
            ->unique('id')
            ->filter(fn (StorageLocation $location): bool => $location->accepts_uploads && ($location->isLocal() || in_array($location->disk, $buckets, true)))
            ->values();
    }

    /** Whether this editor may change who uses which storage. */
    public function canAssign(?User $editor): bool
    {
        return $editor !== null && $this->storage->permitted() && $editor->can('edit_settings');
    }

    /**
     * Replaces this person's own list with these locations, first preferred.
     *
     * @param  list<int>  $ids
     */
    public function replacePersonal(User $account, array $ids): void
    {
        $priorities = [];
        foreach (array_values($ids) as $index => $id) {
            $priorities[$id] = ['priority' => $index + 1];
        }

        $account->storageLocations()->sync($priorities);
    }

    /**
     * The disk a new upload by this person is written to. A requested
     * location that is not one of theirs, or no longer usable, is ignored
     * rather than refused: the upload session validated it when it was
     * created, and anything that changed since is the administrator's doing.
     */
    public function diskFor(User $uploader, ?int $requestedLocationId = null): string
    {
        $usable = $this->usableFor($uploader);

        if ($usable->isEmpty()) {
            return $this->storage->defaultDisk();
        }

        $requested = $requestedLocationId === null ? null : $usable->firstWhere('id', $requestedLocationId);

        return ($requested ?? $usable->firstOrFail())->disk;
    }
}
