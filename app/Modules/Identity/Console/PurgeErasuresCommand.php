<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console;

use App\Models\User;
use App\Modules\Identity\Erasure\AccountEraser;
use App\Modules\Identity\Erasure\ErasureRefused;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgeErasuresCommand extends Command
{
    protected $signature = 'projectsend:purge-erasures';

    protected $description = 'Permanently erase deleted accounts whose grace period has passed (runs daily)';

    public function handle(AccountEraser $eraser): int
    {
        $due = User::onlyTrashed()
            ->whereNotNull('erase_after')
            ->where('erase_after', '<=', now())
            ->get();

        $erased = 0;

        foreach ($due as $user) {
            // Refused by a listener (DeletingAccount): left as it is, and
            // asked again on the next run.
            try {
                $eraser->erase($user);
                $erased++;
            } catch (ErasureRefused $refused) {
                Log::warning("Account {$user->id} was not erased: {$refused->getMessage()}");
            }
        }

        $this->info("Erased {$erased} account(s).");

        return self::SUCCESS;
    }
}
