<?php

declare(strict_types=1);

namespace App\Modules\Files\Jobs;

use App\Models\User;
use App\Modules\Files\Notifications\AdminClientUploadDigestNotification;
use App\Modules\Files\Notifications\AdminClientUploadedNotification;
use App\Modules\Files\Uploads\ClientUploadMail;
use App\Modules\Notifications\PendingBatch;
use App\Modules\Notifications\PendingNotification;
use App\Modules\Platform\Settings\Setting;
use App\Modules\Platform\Settings\Settings;
use App\Modules\Platform\Settings\StorageLocation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

/**
 * Sends whatever one client has uploaded since the last send, as one email
 * per admin address. See ClientUploadMail.
 *
 * Every upload queues one of these, and each stands down while the client
 * is still uploading. The rule is PendingBatch's, shared with the share
 * and comment digests.
 */
class SendClientUploadMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const MAX_HOLD_SECONDS = PendingBatch::MAX_HOLD_SECONDS;

    public function __construct(
        private readonly int $clientId,
        /** Send what is waiting now, without standing down. For a queue that cannot delay. */
        private readonly bool $immediate = false,
    ) {}

    public function handle(Settings $settings, ClientUploadMail $mail): void
    {
        $items = PendingBatch::takeWhenQuiet($this->clientId, ClientUploadMail::TYPE, $this->immediate);

        if ($items === null) {
            return;
        }

        // Asked again at send time: email may have been switched off, or
        // the list emptied, during the wait.
        if ($settings->get(Setting::EmailNotificationsEnabled) !== true) {
            return;
        }

        $client = User::query()->find($this->clientId);

        if ($client === null) {
            return;
        }

        // Each file's storage location by name, when the setting asks for
        // it. Looked up now rather than at upload time: the setting, or
        // the location's name, may have changed during the wait.
        $locationNames = [];
        if ($settings->get(Setting::UploadNotificationNamesLocation) === true) {
            $disks = $items->map(fn (PendingNotification $item): mixed => $item->context['disk'] ?? null)->filter(fn (mixed $disk): bool => is_string($disk))->unique()->values();
            /** @var array<string, string> $locationNames */
            $locationNames = StorageLocation::query()->whereIn('disk', $disks)->pluck('name', 'disk')->all();
        }

        foreach ($mail->addresses() as $address) {
            if ($items->count() === 1) {
                /** @var PendingNotification $item */
                $item = $items->first();
                $disk = $item->context['disk'] ?? null;
                $locationName = is_string($disk) ? ($locationNames[$disk] ?? null) : null;
                $notification = new AdminClientUploadedNotification($client->name, $item->subject_name, (int) ($item->context['file_id'] ?? 0), $locationName);
            } else {
                $notification = new AdminClientUploadDigestNotification($client->name, $client->id, array_values($items->all()), $locationNames);
            }

            Notification::route('mail', $address)->notify($notification);
        }
    }
}
