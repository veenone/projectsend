<?php

declare(strict_types=1);

namespace App\Modules\Files\Uploads;

use InvalidArgumentException;

/**
 * The upload contexts something has claimed, by key. Empty unless a
 * package registers one, and an upload naming a key nobody claimed is
 * refused, so with nothing here every upload is what it always was.
 */
final class UploadContextHandlers
{
    /** @var array<string, UploadContextHandler> */
    private array $handlers = [];

    public function register(string $key, UploadContextHandler $handler): void
    {
        // Two owners for one key would leave which of them authorizes an
        // upload down to boot order.
        if (isset($this->handlers[$key])) {
            throw new InvalidArgumentException("The upload context [{$key}] is already registered.");
        }

        $this->handlers[$key] = $handler;
    }

    public function handlerFor(string $key): ?UploadContextHandler
    {
        return $this->handlers[$key] ?? null;
    }
}
