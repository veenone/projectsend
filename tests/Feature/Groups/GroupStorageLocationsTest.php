<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Groups\Models\Group;
use App\Modules\Identity\Permissions\SystemRole;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('an administrator sets a group\'s storage, replacing what was there', function () {
    [$a, $b, $c] = [bucketLocation(['name' => 'A']), bucketLocation(['name' => 'B']), bucketLocation(['name' => 'C'])];
    applyStorage();
    $group = groupWithStorage([$a, $b], []);

    $this->actingAs($this->admin)
        ->put("/groups/{$group->id}/storage-locations", ['storage_location_ids' => [$a->id, $c->id]])
        ->assertRedirect();

    expect($group->storageLocations()->pluck('storage_locations.id')->sort()->values()->all())
        ->toBe(collect([$a->id, $c->id])->sort()->values()->all());
});

test('an editor who can edit groups but not settings cannot set group storage', function () {
    $a = bucketLocation();
    applyStorage();
    $group = groupWithStorage([$a], []);
    $editor = User::factory()->role(SystemRole::AccountManager)->create();

    $this->actingAs($editor)
        ->put("/groups/{$group->id}/storage-locations", ['storage_location_ids' => []])
        ->assertForbidden();

    expect($group->storageLocations()->pluck('storage_locations.id')->all())->toBe([$a->id]);
});

test('a location that does not accept uploads cannot be assigned to a group', function () {
    $closed = bucketLocation(['accepts_uploads' => false]);
    applyStorage();
    $group = Group::query()->create(['name' => 'Finance']);

    $this->actingAs($this->admin)
        ->putJson("/groups/{$group->id}/storage-locations", ['storage_location_ids' => [$closed->id]])
        ->assertStatus(422)->assertJsonValidationErrors(['storage_location_ids.0']);

    expect($group->storageLocations()->count())->toBe(0);
});

test('the group edit page lists accepting locations and names the hidden ones', function () {
    $open = bucketLocation(['name' => 'EU archive']);
    $closed = bucketLocation(['name' => 'Old MinIO', 'accepts_uploads' => false]);
    applyStorage();
    $group = groupWithStorage([$open, $closed], [User::factory()->client()->create()]);

    $this->actingAs($this->admin)->get("/groups/{$group->id}")->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('storage.can_assign', true)
            ->where('storage.options', fn ($options) => collect($options)->pluck('id')->contains($open->id)
                && ! collect($options)->pluck('id')->contains($closed->id))
            ->where('storage.hidden_names', ['Old MinIO'])
            ->where('storage.assigned_ids', [$open->id])
            ->where('storage.members_count', 1),
    );
});

test('the client settings page flags an auto-group that has storage', function () {
    $group = groupWithStorage([bucketLocation()], []);
    applyStorage();
    app(Settings::class)->set(Setting::ClientsAutoGroup, $group->id);

    $this->actingAs($this->admin)->get('/system/settings/clients')->assertInertia(
        fn (AssertableInertia $page) => $page->where('auto_group_has_storage', true),
    );

    app(Settings::class)->set(Setting::ClientsAutoGroup, 0);
    $this->get('/system/settings/clients')->assertInertia(
        fn (AssertableInertia $page) => $page->where('auto_group_has_storage', false),
    );
});
