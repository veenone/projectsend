<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\File;
use App\Modules\Files\Storage\UploadLocations;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\AccountConversion;
use App\Modules\Identity\Permissions\EnsureSystemRoles;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Platform\Settings\StorageLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Where an upload may go: the person's own list, then the storages of every
 * group they belong to (clients only), each listed once.
 */

beforeEach(function () {
    $this->admin = User::factory()->create();
});

/** @return list<int> */
function usableIds(User $user): array
{
    return app(UploadLocations::class)->usableFor($user)->pluck('id')->all();
}

test('a client gets their groups\' storage until it stops taking uploads or the group is deleted', function (string $change) {
    $client = User::factory()->client()->create();
    $location = bucketLocation();
    $group = groupWithStorage([$location], [$client]);
    applyStorage();

    expect(usableIds($client))->toBe([$location->id])
        ->and(resolvedDiskFor($client))->toBe($location->disk);

    $change === 'closed' ? $location->forceFill(['accepts_uploads' => false])->save() : $group->delete();
    applyStorage();

    expect(usableIds($client))->toBe([]);
})->with(['closed', 'group deleted']);

test('personal comes first, then group storages default-first then by name', function () {
    $client = User::factory()->client()->create();
    $personal = bucketLocation(['name' => 'Personal']);
    $z = bucketLocation(['name' => 'Z-bucket']);
    $a = bucketLocation(['name' => 'A-bucket']);
    assignLocations($client, [$personal]);
    groupWithStorage([$z, $a], [$client]);
    applyStorage();

    expect(usableIds($client))->toBe([$personal->id, $a->id, $z->id]);

    makeDefault($z);

    expect(usableIds($client))->toBe([$personal->id, $z->id, $a->id]);
});

test('a storage given more than once is listed once, at its first position', function () {
    $client = User::factory()->client()->create();
    [$a, $b, $c] = [bucketLocation(['name' => 'A']), bucketLocation(['name' => 'B']), bucketLocation(['name' => 'C'])];
    assignLocations($client, [$a, $b]);
    groupWithStorage([$b, $c], [$client]);
    groupWithStorage([$c], [$client]);
    applyStorage();

    expect(usableIds($client))->toBe([$a->id, $b->id, $c->id]);
});

test('staff use their personal list, in its saved priority rather than row order', function () {
    $staff = User::factory()->create();
    $a = bucketLocation(['name' => 'A']);
    $b = bucketLocation(['name' => 'B']);
    // Rows written A then B, then reordered in place: neither the ids nor
    // the pivot rows are in the preferred order any more.
    assignLocations($staff, [$a, $b]);
    assignLocations($staff, [$b, $a]);
    applyStorage();

    expect(usableIds($staff))->toBe([$b->id, $a->id])
        ->and(resolvedDiskFor($staff))->toBe($b->disk);
});

test('staff with nothing of their own use the default, even in a group with storage', function () {
    $location = bucketLocation();
    $group = Group::query()->create(['name' => 'Ops']);
    $group->storageLocations()->attach($location->id);
    DB::statement('INSERT INTO group_members (group_id, user_id) VALUES (?, ?)', [$group->id, $this->admin->id]);
    applyStorage();

    expect(usableIds($this->admin))->toBe([])
        ->and(resolvedDiskFor($this->admin, $location->id))->toBe('files');
});

test('leaving a group takes its storage away at upload', function () {
    $client = uploadingClient();
    $location = bucketLocation();
    $group = groupWithStorage([$location], [$client]);
    applyStorage();
    $group->members()->detach($client->id);

    $this->actingAs($client)->postJson('/uploads', [
        'filename' => 'statement.pdf',
        'size' => 11,
        'type' => 'application/pdf',
        'storage_location_id' => $location->id,
    ])->assertStatus(422)->assertJsonValidationErrors(['storage_location_id']);
});

test('an open session whose group storage was removed falls back to the first option', function () {
    $client = User::factory()->client()->create();
    $first = bucketLocation(['name' => 'A']);
    $removed = bucketLocation(['name' => 'B']);
    $group = groupWithStorage([$first, $removed], [$client]);
    applyStorage();
    $group->storageLocations()->detach($removed->id);

    expect(resolvedDiskFor($client, $removed->id))->toBe($first->disk);
});

test('converting an account either way clears their personal locations', function (string $to) {
    $account = $to === 'staff' ? User::factory()->client()->create() : User::factory()->role(SystemRole::Uploader)->create();
    assignLocations($account, [bucketLocation(), bucketLocation()]);

    $result = $to === 'staff'
        ? app(AccountConversion::class)->toStaff($account, app(EnsureSystemRoles::class)->materialize(SystemRole::Uploader)->id)
        : app(AccountConversion::class)->toClient($account, User::factory()->create());
    $logged = ActivityLog::query()->where('subject_id', $account->id)
        ->where('action', $to === 'staff' ? Action::AccountConvertedToStaff : Action::AccountConvertedToClient)->first();

    expect($account->storageLocations()->count())->toBe(0)
        ->and($result['storage_locations_cleared'])->toBe(2)
        ->and($logged->context['storage_locations_cleared'])->toBe(2);
})->with(['staff', 'client']);

function staffUpdatePayload(User $staff, array $extra = []): array
{
    return [
        'name' => $staff->name,
        'email' => $staff->email,
        'role_id' => $staff->role_id,
        'active' => true,
        ...$extra,
    ];
}

test('an administrator sets a staff member\'s locations in order', function () {
    [$a, $b] = [bucketLocation(['name' => 'A']), bucketLocation(['name' => 'B'])];
    applyStorage();
    $staff = User::factory()->role(SystemRole::Uploader)->create();

    $this->actingAs($this->admin)
        ->patch("/users/{$staff->id}", staffUpdatePayload($staff, ['storage_location_ids' => [$b->id, $a->id]]))
        ->assertSessionHasNoErrors();

    expect($staff->storageLocations()->pluck('storage_locations.id')->all())->toBe([$b->id, $a->id])
        ->and($staff->storageLocations()->pluck('priority')->map(fn ($p): int => (int) $p)->all())->toBe([1, 2]);
});

test('a staff member who cannot change settings cannot change another staff member\'s locations', function () {
    $a = bucketLocation();
    applyStorage();
    $staff = User::factory()->role(SystemRole::Uploader)->create();
    $editor = User::factory()->role(SystemRole::AccountManager)->create();

    $this->actingAs($editor)
        ->patch("/users/{$staff->id}", staffUpdatePayload($staff, ['storage_location_ids' => [$a->id]]));

    expect($staff->storageLocations()->count())->toBe(0);
});

test('the staff edit page offers the personal card only where storage is permitted', function () {
    $a = bucketLocation(['name' => 'A']);
    applyStorage();
    $staff = User::factory()->role(SystemRole::Uploader)->create();
    assignLocations($staff, [$a]);

    $this->actingAs($this->admin)->get("/users/{$staff->id}")->assertInertia(
        fn (Assert $page) => $page
            ->where('storage.can_assign', true)
            ->where('storage.assigned_ids', [$a->id])
            ->missing('storage.from_groups'),
    );
});

test('the client edit page shows storage from groups once, with the groups it comes from', function () {
    [$a, $c] = [bucketLocation(['name' => 'A']), bucketLocation(['name' => 'C'])];
    applyStorage();
    $client = User::factory()->client()->create();
    assignLocations($client, [$a]);
    $finance = groupWithStorage([$a, $c], [$client]);
    $finance->update(['name' => 'Finance']);
    $legal = groupWithStorage([$c], [$client]);
    $legal->update(['name' => 'Legal']);

    $this->actingAs($this->admin)->get("/clients/{$client->id}")->assertInertia(
        fn (Assert $page) => $page
            ->where('storage.from_groups', [['id' => $c->id, 'name' => 'C', 'groups' => ['Finance', 'Legal']]])
            ->where('storage.also_in_groups', ['A']),
    );
});

test('the card leaves out or marks what the upload screen would not offer', function () {
    $closed = bucketLocation(['name' => 'Closed', 'accepts_uploads' => false]);
    $unset = bucketLocation(['name' => 'Unset', 'bucket' => null]);
    $open = bucketLocation(['name' => 'Open']);
    $groupClosed = bucketLocation(['name' => 'Group closed', 'accepts_uploads' => false]);
    $groupDeleted = bucketLocation(['name' => 'Group deleted']);
    applyStorage();
    $client = User::factory()->client()->create();
    assignLocations($client, [$closed, $unset, $open]);
    groupWithStorage([$groupDeleted], [$client])->delete();
    groupWithStorage([$groupClosed], [$client]);

    $this->actingAs($this->admin)->get("/clients/{$client->id}")->assertInertia(
        function (Assert $page) use ($client, $closed, $unset, $open) {
            $storage = $page->toArray()['props']['storage'];
            $usable = collect($storage['options'])->pluck('usable', 'id');

            expect($storage['from_groups'])->toBe([])
                ->and($usable[$closed->id])->toBeFalse()
                ->and($usable[$unset->id])->toBeFalse()
                ->and($usable[$open->id])->toBeTrue()
                // The same answer the upload screen gives.
                ->and(usableIds($client))->toBe([$open->id]);
        },
    );
});

// ---- Staff uploads -----------------------------------------------------------

test('the staff upload page lists their usable locations, preferred first', function () {
    $eu = bucketLocation(['name' => 'Archive (EU)', 'client_note' => 'Kept in Frankfurt']);
    $local = StorageLocation::query()->where('disk', 'files')->firstOrFail();
    assignLocations($this->admin, [$eu, $local]);
    applyStorage();

    $this->actingAs($this->admin)->get(route('files.create'))->assertInertia(
        fn (Assert $page) => $page
            ->where('storage_locations.0', ['id' => $eu->id, 'name' => 'Archive (EU)', 'note' => 'Kept in Frankfurt'])
            ->where('storage_locations.1.id', $local->id)
            ->missing('storage_locations.0.bucket'),
    );
});

test('a staff member\'s chunked upload lands in the location they chose', function () {
    Storage::fake('files');
    $first = bucketLocation(['name' => 'First']);
    $second = bucketLocation(['name' => 'Second']);
    assignLocations($this->admin, [$first, $second]);
    applyStorage();
    Storage::fake($first->disk);
    Storage::fake($second->disk);

    $this->actingAs($this->admin);
    uploadChunkedAsClient(['storage_location_id' => $second->id])->assertOk();

    $file = File::query()->latest('id')->firstOrFail();
    expect($file->disk)->toBe($second->disk);
    Storage::disk($second->disk)->assertExists($file->path);
});
