<?php

declare(strict_types=1);

namespace App\Modules\Audit;

use InvalidArgumentException;

/**
 * Actions packages have added beside the core vocabulary, by key. Empty
 * unless a package registers one, so with nothing here the activity log
 * and its filter list are what they always were.
 */
final class ExtraActions
{
    /** @var array<string, ExtraAction> */
    private array $actions = [];

    public function register(string $key, string $template, string $description): void
    {
        // A core key would be read back as the core case, and a second
        // registration would leave two owners for one stored value.
        if (Action::tryFrom($key) !== null || isset($this->actions[$key])) {
            throw new InvalidArgumentException("The activity log action [{$key}] is already defined.");
        }

        $this->actions[$key] = new ExtraAction($key, $template, $description);
    }

    /**
     * @return array<string, ExtraAction>
     */
    public function all(): array
    {
        return $this->actions;
    }

    public function has(string $key): bool
    {
        return isset($this->actions[$key]);
    }

    public function find(string $key): ?ExtraAction
    {
        return $this->actions[$key] ?? null;
    }
}
