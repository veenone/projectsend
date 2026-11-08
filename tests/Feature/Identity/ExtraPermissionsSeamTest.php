<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RolePermission;
use App\Modules\Identity\Permissions\ExtraPermissions;
use App\Modules\Identity\Permissions\Permission;
use App\Modules\Identity\Permissions\PermissionCategory;
use Inertia\Testing\AssertableInertia;

function catalogKeys(array $catalog): array
{
    return collect($catalog)->flatMap(fn (array $group): array => array_column($group['permissions'], 'key'))->all();
}

test('with nothing registered the roles editor lists exactly the core permissions', function () {
    $this->actingAs(User::factory()->create());
    $role = Role::query()->create(['name' => 'Auditor']);

    $this->get('/roles')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('total_permissions', count(Permission::cases())));

    $this->get("/roles/{$role->id}")->assertInertia(function (AssertableInertia $page) {
        $catalog = $page->toArray()['props']['catalog'];

        expect(array_column($catalog, 'key'))->toBe(array_map(fn (PermissionCategory $c): string => $c->value, PermissionCategory::cases()))
            ->and(catalogKeys($catalog))->toBe(array_map(fn (Permission $p): string => $p->value, Permission::cases()));
    });
});

test('a registered permission shows in its category and can be granted to a role', function () {
    app(ExtraPermissions::class)->register('evidence.manage', 'Manage evidence', PermissionCategory::System);
    $this->actingAs(User::factory()->create());
    $role = Role::query()->create(['name' => 'Auditor']);

    $this->get('/roles')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('total_permissions', count(Permission::cases()) + 1));

    $this->get("/roles/{$role->id}")->assertInertia(function (AssertableInertia $page) {
        $system = collect($page->toArray()['props']['catalog'])->firstWhere('key', PermissionCategory::System->value);

        expect(end($system['permissions']))->toBe(['key' => 'evidence.manage', 'label' => 'Manage evidence']);
    });

    $this->patch("/roles/{$role->id}", ['name' => 'Auditor', 'permissions' => ['view_actions_log', 'evidence.manage']])
        ->assertSessionHasNoErrors();

    expect(RolePermission::query()->where('role_id', $role->id)->where('permission', 'evidence.manage')->exists())->toBeTrue();

    $holder = User::factory()->create(['role_id' => $role->id]);
    $other = staffWithPermissions(['view_actions_log']);

    expect($holder->can('evidence.manage'))->toBeTrue()
        ->and($other->can('evidence.manage'))->toBeFalse();
});

test('administrators hold every registered permission without a row', function () {
    app(ExtraPermissions::class)->register('evidence.manage', 'Manage evidence', PermissionCategory::System);
    $admin = User::factory()->create();

    expect(RolePermission::query()->where('permission', 'evidence.manage')->exists())->toBeFalse()
        ->and($admin->can('evidence.manage'))->toBeTrue();
});

test('a key that collides with a core permission is refused', function () {
    $registry = app(ExtraPermissions::class);

    expect(fn () => $registry->register(Permission::Upload->value, 'Upload', PermissionCategory::Files))
        ->toThrow(InvalidArgumentException::class);

    $registry->register('evidence.manage', 'Manage evidence', PermissionCategory::System);

    expect(fn () => $registry->register('evidence.manage', 'Again', PermissionCategory::System))
        ->toThrow(InvalidArgumentException::class)
        ->and($registry->has(Permission::Upload->value))->toBeFalse()
        ->and(array_keys($registry->all()))->toBe(['evidence.manage']);
});

test('an editor cannot grant a registered permission their own role lacks', function () {
    app(ExtraPermissions::class)->register('evidence.manage', 'Manage evidence', PermissionCategory::System);
    $role = Role::query()->create(['name' => 'Uploader']);

    $lacking = staffWithPermissions(['manage_users', 'edit_users', 'upload']);
    $this->actingAs($lacking)->patch("/roles/{$role->id}", ['name' => 'Uploader', 'permissions' => ['upload', 'evidence.manage']])
        ->assertSessionHasErrors('permissions');
    expect($role->permissions()->pluck('permission')->all())->toBe([]);

    $holding = staffWithPermissions(['manage_users', 'edit_users', 'upload', 'evidence.manage']);
    $this->actingAs($holding)->patch("/roles/{$role->id}", ['name' => 'Uploader', 'permissions' => ['upload', 'evidence.manage']])
        ->assertSessionHasNoErrors();
    expect($role->permissions()->pluck('permission')->sort()->values()->all())->toBe(['evidence.manage', 'upload']);
});
