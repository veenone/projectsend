<?php

declare(strict_types=1);

namespace App\Modules\Audit;

/**
 * An action a package adds beside the core vocabulary. Template and
 * description are English and double as translation keys, like
 * Action's own.
 */
final readonly class ExtraAction implements LogAction
{
    public function __construct(
        private string $key,
        private string $template,
        private string $description,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function template(): string
    {
        return $this->template;
    }

    public function description(): string
    {
        return $this->description;
    }
}
