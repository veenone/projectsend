<?php

declare(strict_types=1);

namespace App\Modules\Files\Storage;

use App\Models\User;
use App\Modules\Files\Models\File;
use App\Modules\Platform\Settings\ExternalStorageConfigApplier;
use App\Modules\Platform\Settings\StorageLocation;

/**
 * What the "Storage locations" card on a client's or staff member's edit
 * page shows. Null where this edition has no storage locations to give.
 * Only someone who may change the installation's settings may change them:
 * they decide which bucket a person's files are written to.
 */
class StorageCardProps
{
    public function __construct(
        private readonly ExternalStorageConfigApplier $storage,
        private readonly UploadLocations $uploadLocations,
    ) {}

    /**
     * `from_groups` and `also_in_groups` are for clients only: the storages
     * their groups give that the upload screen would actually offer, each
     * with the groups it comes from, and the personal ones that a group
     * gives as well.
     *
     * @return array{can_assign: bool, options: list<array{id: int, name: string, local: bool, location: string, accepts_uploads: bool, is_default: bool, usable: bool}>, assigned_ids: list<int>, files_count: int, from_groups?: list<array{id: int, name: string, groups: list<string>}>, also_in_groups?: list<string>}|null
     */
    public function for(?User $viewer, User $account): ?array
    {
        if (! $this->storage->permitted()) {
            return null;
        }

        $assignedIds = array_values(array_map(intval(...), $account->storageLocations()->pluck('storage_locations.id')->all()));
        $buckets = array_column($this->storage->externalLocations(), 'disk');

        $props = [
            'can_assign' => $this->uploadLocations->canAssign($viewer),
            'options' => array_values(StorageLocation::query()->orderByDesc('is_default')->orderBy('name')->get()
                ->map(fn (StorageLocation $location): array => [
                    'id' => $location->id,
                    'name' => $location->name,
                    'local' => $location->isLocal(),
                    'location' => $location->location(),
                    'accepts_uploads' => $location->accepts_uploads,
                    'is_default' => $location->is_default,
                    // The test usableFor() applies, so the card's preview
                    // of the upload screen can follow unsaved reordering.
                    'usable' => $location->accepts_uploads && ($location->isLocal() || in_array($location->disk, $buckets, true)),
                ])->values()->all()),
            'assigned_ids' => $assignedIds,
            'files_count' => File::withTrashed()->where('uploaded_by', $account->id)->count(),
        ];

        if (! $account->isClient()) {
            return $props;
        }

        $groupNames = [];
        foreach ($account->memberOfGroups()->with('storageLocations')->get() as $group) {
            foreach ($group->storageLocations as $location) {
                $groupNames[$location->id][] = $group->name;
            }
        }

        $usable = $this->uploadLocations->usableFor($account)->filter(fn (StorageLocation $l): bool => isset($groupNames[$l->id]));
        $named = fn (StorageLocation $location): array => [
            'id' => $location->id,
            'name' => $location->name,
            'groups' => collect($groupNames[$location->id])->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all(),
        ];

        $props['from_groups'] = $usable->reject(fn (StorageLocation $l): bool => in_array($l->id, $assignedIds, true))->map($named)->values()->all();
        $props['also_in_groups'] = $usable->filter(fn (StorageLocation $l): bool => in_array($l->id, $assignedIds, true))->pluck('name')->values()->all();

        return $props;
    }
}
