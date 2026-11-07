<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Audit\Action;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Storage\UploadLocations;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\AccountConversion;
use App\Modules\Identity\Permissions\EnsureSystemRoles;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Platform\Settings\StorageLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

test('client with only group storage gets it', function () {
    $client = User::factory()->client()->create();
    $g1 = bucketLocation();
    groupWithStorage([$g1], [$client]);
    applyStorage();

    expect(usableIds($client))->toBe([$g1->id])
        ->and(resolvedDiskFor($client))->toBe($g1->disk);
});

test('personal comes first, then group storages default-first then by name', function () {
    $client = User::factory()->client()->create();
    $personal = bucketLocation(['name' => 'Personal']);
    $z = bucketLocation(['name' => 'Z-bucket']);
    $a = bucketLocation(['name' => 'A-bucket']);
    assignPersonalLocations($client, [$personal]);
    groupWithStorage([$z, $a], [$client]);
    applyStorage();

    expect(usableIds($client))->toBe([$personal->id, $a->id, $z->id]);

    makeDefault($z);

    expect(usableIds($client))->toBe([$personal->id, $z->id, $a->id]);
});

test('a storage in both lists appears once, at its personal position', function () {
    $client = User::factory()->client()->create();
    [$a, $b, $c] = [bucketLocation(['name' => 'A']), bucketLocation(['name' => 'B']), bucketLocation(['name' => 'C'])];
    assignPersonalLocations($client, [$a, $b]);
    groupWithStorage([$b, $c], [$client]);
    applyStorage();

    expect(usableIds($client))->toBe([$a->id, $b->id, $c->id]);
});

test('two groups sharing a storage list it once', function () {
    $client = User::factory()->client()->create();
    $shared = bucketLocation();
    groupWithStorage([$shared], [$client]);
    groupWithStorage([$shared], [$client]);
    applyStorage();

    expect(usableIds($client))->toBe([$shared->id]);
});

test('staff use their personal list', function () {
    $staff = User::factory()->create();
    $a = bucketLocation(['name' => 'A']);
    $b = bucketLocation(['name' => 'B']);
    assignPersonalLocations($staff, [$a, $b]);
    applyStorage();

    expect(usableIds($staff))->toBe([$a->id, $b->id])
        ->and(resolvedDiskFor($staff))->toBe($a->disk);
});

test('staff with nothing assigned use the default', function () {
    $location = bucketLocation();
    applyStorage();

    expect(usableIds($this->admin))->toBe([])
        ->and(resolvedDiskFor($this->admin, $location->id))->toBe('files');
});

test('staff group rows never apply', function () {
    $staff = User::factory()->create();
    $group = Group::query()->create(['name' => 'Ops']);
    $group->storageLocations()->attach(bucketLocation()->id);
    DB::statement('INSERT INTO group_members (group_id, user_id) VALUES (?, ?)', [$group->id, $staff->id]);
    applyStorage();

    expect(usableIds($staff))->toBe([]);
});

test('a group storage that stops accepting uploads drops out', function () {
    $client = User::factory()->client()->create();
    $location = bucketLocation();
    groupWithStorage([$location], [$client]);
    applyStorage();
    expect(usableIds($client))->toBe([$location->id]);

    $location->forceFill(['accepts_uploads' => false])->save();
    applyStorage();

    expect(usableIds($client))->toBe([]);
});

test('a soft-deleted group no longer gives its storage', function () {
    $client = User::factory()->client()->create();
    $location = bucketLocation();
    $group = groupWithStorage([$location], [$client]);
    applyStorage();
    expect(usableIds($client))->toBe([$location->id]);

    $group->delete();

    expect(usableIds($client))->toBe([]);
});

test('leaving a group takes its storage away at upload', function () {
    $client = User::factory()->client()->create();
    grantStorageClientUpload($client);
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

test('converting a client to staff clears their personal locations', function () {
    $client = User::factory()->client()->create();
    assignPersonalLocations($client, [bucketLocation(), bucketLocation()]);

    app(AccountConversion::class)->toStaff($client, app(EnsureSystemRoles::class)->materialize(SystemRole::Uploader)->id);

    expect($client->storageLocations()->count())->toBe(0)
        ->and(ActivityLog::query()->where('action', Action::AccountConvertedToStaff)->where('subject_id', $client->id)->first()->context['storage_locations_cleared'])->toBe(2);
});

test('converting staff to a client clears their personal locations', function () {
    $staff = User::factory()->role(SystemRole::Uploader)->create();
    assignPersonalLocations($staff, [bucketLocation(), bucketLocation()]);

    $result = app(AccountConversion::class)->toClient($staff);

    expect($staff->storageLocations()->count())->toBe(0)
        ->and($result['storage_locations_cleared'])->toBe(2)
        ->and(ActivityLog::query()->where('action', Action::AccountConvertedToClient)->where('subject_id', $staff->id)->first()->context['storage_locations_cleared'])->toBe(2);
});

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
    assignPersonalLocations($staff, [$a]);

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
    assignPersonalLocations($client, [$a]);
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

test('storage from groups leaves out what the upload screen would not offer', function () {
    $closed = bucketLocation(['name' => 'Closed', 'accepts_uploads' => false]);
    $open = bucketLocation(['name' => 'Open']);
    applyStorage();
    $client = User::factory()->client()->create();
    $deleted = groupWithStorage([$open], [$client]);
    $deleted->delete();
    groupWithStorage([$closed], [$client]);

    $this->actingAs($this->admin)->get("/clients/{$client->id}")->assertInertia(
        fn (Assert $page) => $page->where('storage.from_groups', []),
    );
});
