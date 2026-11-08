<?php

declare(strict_types=1);

namespace App\Modules\Identity\Permissions;

/**
 * A permission a package adds beside the core vocabulary. The label is
 * English and doubles as the translation key, like Permission::label().
 */
final readonly class ExtraPermission
{
    public function __construct(
        public string $key,
        public string $label,
        public PermissionCategory $category,
    ) {}
}
