<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use App\Models\User;

/**
 * "May this account be deleted?" Asked once on every path that deletes or
 * erases an account, before anything is deleted or reassigned and before
 * the transaction that does it starts.
 *
 * $path says which door it is: 'delete' for an administrator deleting a
 * staff member or a client (web or API), 'self' for somebody deleting
 * their own account, 'erase' for permanent erasure (the console command
 * and the scheduled purge).
 *
 * A package that has its own reason to keep an account — content it is
 * obliged to retain, say — refuses with a message, and the person asking
 * is shown that message. Core has no opinion of its own: with nothing
 * listening, every path deletes exactly as it did before.
 *
 * Listened to by *string* class name from a package, same as every other
 * hook here — see docs/extension-points-architecture.md.
 */
final class DeletingAccount
{
    public ?string $refusal = null;

    public function __construct(
        public readonly User $account,
        /** 'delete' | 'erase' | 'self' */
        public readonly string $path,
    ) {}

    /**
     * The first refusal wins, so the message shown is the one from the
     * listener that stopped it.
     */
    public function refuse(string $message): void
    {
        $this->refusal ??= $message;
    }

    public function refused(): bool
    {
        return $this->refusal !== null;
    }
}
