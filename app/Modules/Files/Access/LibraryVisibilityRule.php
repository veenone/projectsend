<?php

declare(strict_types=1);

namespace App\Modules\Files\Access;

use App\Models\User;
use App\Modules\Audit\ActivityLog;
use Illuminate\Database\Eloquent\Builder;

/**
 * A package's say over which parts of the library a staff member sees,
 * on top of StaffLibraryScope. Folder ids are subtree roots: each one
 * stands for that folder and everything inside it.
 */
interface LibraryVisibilityRule
{
    /**
     * Subtrees this viewer must not see, whoever they are — unrestricted
     * staff included.
     *
     * @return list<int>
     */
    public function hiddenFolderIds(User $viewer): array;

    /**
     * Subtrees a client-scoped viewer sees in addition to their own
     * library. Unrestricted staff already see everything, so this is
     * not asked for them.
     *
     * @return list<int>
     */
    public function grantedFolderIds(User $viewer): array;

    /**
     * Narrow the activity log for the package's own subject types. File
     * and folder subjects are handled by ActivityLogScope already.
     *
     * @param  Builder<ActivityLog>  $query
     */
    public function scopeActivityLog(User $viewer, Builder $query): void;
}
