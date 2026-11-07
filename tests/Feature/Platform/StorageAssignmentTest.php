<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Files\Storage\UploadLocations;
use App\Modules\Groups\Models\Group;
use App\Modules\Platform\Settings\StorageLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Where an upload may go: the person's own list, then the storages of every
 * group they belong to (clients only), each listed once.
 */

beforeEach(function () {
    $this->admin = User::factory()->create();
});

/**
 * @param  list<StorageLocation>  $locations
 * @param  list<User>  $members
 */
function groupWithStorage(array $locations, array $members): Group
{
    $group = Group::query()->create(['name' => 'Group '.Str::random(5)]);
    $group->storageLocations()->attach(array_map(fn (StorageLocation $l): int => $l->id, $locations));
    $group->members()->attach(array_map(fn (User $u): int => $u->id, $members));

    return $group;
}

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
