<?php

declare(strict_types=1);

namespace App\Modules\Audit\Casts;

use App\Modules\Audit\Action;
use App\Modules\Audit\ExtraActions;
use App\Modules\Audit\LogAction;
use App\Modules\Audit\UnknownAction;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Reads the action column as a core case, a registered action, or — for
 * a key whose package is gone — an UnknownAction, never an exception.
 *
 * @implements CastsAttributes<LogAction, LogAction|string>
 */
class LogActionCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?LogAction
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;

        return Action::tryFrom($value) ?? app(ExtraActions::class)->find($value) ?? new UnknownAction($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value instanceof LogAction) {
            return $value->key();
        }

        return $value === null ? null : (string) $value;
    }
}
