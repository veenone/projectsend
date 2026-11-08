<?php

declare(strict_types=1);

namespace App\Modules\Identity\Permissions;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Permissions packages have added beside the core vocabulary, by key.
 * Empty unless a package registers one, so with nothing here the roles
 * editor and every permission check are what they always were.
 */
final class ExtraPermissions
{
    /** @var array<string, ExtraPermission> */
    private array $permissions = [];

    public function register(string $key, string $label, PermissionCategory $category): void
    {
        // A core key would end up with two Gate definitions and two rows
        // in the editor, and a second registration with two owners.
        if (Permission::tryFrom($key) !== null || isset($this->permissions[$key])) {
            throw new InvalidArgumentException("The permission [{$key}] is already defined.");
        }

        $this->permissions[$key] = new ExtraPermission($key, $label, $category);

        // Defined here rather than in a boot loop, so it does not matter
        // whether the registering provider boots before or after Identity.
        Gate::define($key, fn (User $user): bool => app(PermissionChecker::class)->allowsKey($user, $key));
    }

    /**
     * @return array<string, ExtraPermission>
     */
    public function all(): array
    {
        return $this->permissions;
    }

    public function has(string $key): bool
    {
        return isset($this->permissions[$key]);
    }
}
