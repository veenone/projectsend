<?php

declare(strict_types=1);

namespace App\Modules\Files\Access;

use App\Models\User;
use App\Modules\Audit\ActivityLog;
use App\Modules\Files\Models\Folder;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Every LibraryVisibilityRule packages have registered. Empty unless one
 * does, and then every answer here is empty too, so StaffLibraryScope and
 * ActivityLogScope behave exactly as they always have.
 *
 * A singleton: rules are registered once, from a package's boot(), and
 * have to outlast the scoped instances a queue worker drops between jobs.
 * Nothing here is remembered per viewer — StaffLibraryScope keeps that,
 * for the request.
 */
class LibraryVisibilityRules
{
    /** @var list<LibraryVisibilityRule> */
    private array $rules = [];

    public function register(LibraryVisibilityRule $rule): void
    {
        if (in_array($rule, $this->rules, true)) {
            throw new InvalidArgumentException('This library visibility rule is already registered.');
        }

        $this->rules[] = $rule;
    }

    /**
     * Every folder id inside a subtree some rule hides from this viewer.
     *
     * @return list<int>
     */
    public function hiddenSubtreeIds(User $viewer): array
    {
        return $this->subtrees(
            array_merge(...array_map(fn (LibraryVisibilityRule $rule): array => $rule->hiddenFolderIds($viewer), $this->rules)),
        );
    }

    /**
     * Every folder id inside a subtree some rule opens to this viewer.
     *
     * @return list<int>
     */
    public function grantedSubtreeIds(User $viewer): array
    {
        return $this->subtrees(
            array_merge(...array_map(fn (LibraryVisibilityRule $rule): array => $rule->grantedFolderIds($viewer), $this->rules)),
        );
    }

    /**
     * @param  Builder<ActivityLog>  $query
     */
    public function scopeActivityLog(User $viewer, Builder $query): void
    {
        foreach ($this->rules as $rule) {
            $rule->scopeActivityLog($viewer, $query);
        }
    }

    /**
     * The roots and every folder below them, by materialised path. Trashed
     * folders included: the activity log still names files that were
     * deleted along with their folder, and those stay hidden too.
     *
     * @param  array<int|string>  $roots
     * @return list<int>
     */
    private function subtrees(array $roots): array
    {
        $roots = array_values(array_unique(array_map(intval(...), $roots)));

        if ($roots === []) {
            return [];
        }

        return array_values(Folder::withTrashed()
            ->where(function (Builder $query) use ($roots): void {
                $query->whereIn('id', $roots);

                foreach ($roots as $root) {
                    $query->orWhere('path', 'like', "%/{$root}/%");
                }
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all());
    }
}
