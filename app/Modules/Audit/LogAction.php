<?php

declare(strict_types=1);

namespace App\Modules\Audit;

/**
 * Anything the activity log can record: a core Action case, an action a
 * package registered, or a stored key nothing recognises any more.
 */
interface LogAction
{
    /** The stable identifier stored in the action column. */
    public function key(): string;

    /** Sentence for a log row, with :placeholders (see Action::template()). */
    public function template(): string;

    /** Generic description for filter dropdowns. */
    public function description(): string;
}
