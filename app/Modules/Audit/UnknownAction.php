<?php

declare(strict_types=1);

namespace App\Modules\Audit;

/**
 * A stored key that is neither a core action nor registered — typically
 * written by a package that has since been removed. The row is still
 * history, so it renders with a generic sentence naming the key.
 */
final readonly class UnknownAction implements LogAction
{
    public function __construct(
        private string $key,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    // Translated and filled here: the key is not a placeholder the row's
    // replacements carry, and the frontend renders an already-translated
    // string unchanged.
    public function template(): string
    {
        return __('Performed ":action"', ['action' => $this->key]);
    }

    public function description(): string
    {
        return $this->template();
    }
}
