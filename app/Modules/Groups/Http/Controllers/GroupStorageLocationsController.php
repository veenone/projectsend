<?php

declare(strict_types=1);

namespace App\Modules\Groups\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Files\Access\StaffLibraryScope;
use App\Modules\Files\Storage\UploadLocations;
use App\Modules\Groups\Models\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GroupStorageLocationsController extends Controller
{
    public function __construct(
        private readonly StaffLibraryScope $scope,
        private readonly UploadLocations $uploadLocations,
    ) {}

    /**
     * Replaces the group's storage locations with the posted ones, keeping
     * any it holds that no longer accept uploads. Which bucket a group's
     * members write to is a setting of the installation, so editing the
     * group is not enough.
     */
    public function update(Request $request, Group $group): RedirectResponse
    {
        $editor = $request->user();
        assert($editor !== null);

        abort_unless($this->scope->allowsGroupChange($editor, $group), 404);
        abort_unless($this->uploadLocations->canAssign($editor), 403);

        $validated = $request->validate([
            'storage_location_ids' => ['present', 'array'],
            'storage_location_ids.*' => ['integer', 'distinct', Rule::exists('storage_locations', 'id')->where('accepts_uploads', true)],
        ]);

        // The tab only lists locations that accept uploads. The others it
        // holds are shown as hidden, not removed, so they stay.
        $hidden = $group->storageLocations()->where('accepts_uploads', false)->pluck('storage_locations.id')->all();

        $group->storageLocations()->sync([...$validated['storage_location_ids'], ...$hidden]);

        return back()->with('success', __('Group updated.'));
    }
}
