<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use App\Models\User;

/**
 * "May this account change between staff and client?" Asked once, first,
 * by AccountConversion's guard for each direction, before anything else is
 * checked or written.
 *
 * $direction is 'to_client' for a staff member becoming a client and
 * 'to_staff' for a client becoming a staff member.
 *
 * A package with its own reason to keep an account where it is (content
 * it holds that the other population must never see, say) refuses with a
 * message, and the person converting is shown that message. Core has no
 * opinion of its own: with nothing listening, every conversion goes ahead
 * exactly as it did before.
 *
 * Listened to by *string* class name from a package, same as every other
 * hook here — see docs/extension-points-architecture.md.
 */
final class ConvertingAccount
{
    public ?string $refusal = null;

    public function __construct(
        public readonly User $account,
        /** 'to_client' | 'to_staff' */
        public readonly string $direction,
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
