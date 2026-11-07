<?php

declare(strict_types=1);

namespace App\Modules\Files\Notifications;

use App\Modules\Notifications\PendingNotification;
use App\Modules\Platform\Notifications\EmailTemplateSlot;
use App\Modules\Platform\Notifications\Concerns\RendersOverridableMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One email for a burst of uploads by one client, to an address in
 * Setting::AdminNotificationEmails (#1840). Uploading a folder of 200
 * drawings used to send 200 copies of AdminClientUploadedNotification.
 *
 * Collected by ClientUploadMail and sent by SendClientUploadMail. A burst
 * of one still goes out as AdminClientUploadedNotification, so a single
 * upload reads exactly as it always has.
 *
 * Like FileShareDigestNotification, the customizable template is the intro
 * line only; every file name is appended after it as its own line.
 */
class AdminClientUploadDigestNotification extends Notification implements ShouldQueue
{
    use Queueable, RendersOverridableMail;

    /**
     * @param  list<PendingNotification>  $items
     * @param  array<string, string>  $locationNames  Storage location names by disk; filled only when Setting::UploadNotificationNamesLocation is on.
     */
    public function __construct(
        private readonly string $clientName,
        private readonly int $clientId,
        private readonly array $items,
        private readonly array $locationNames = [],
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = count($this->items);

        if (($override = $this->overrideOrNull(EmailTemplateSlot::AdminClientUploadDigest)) !== null) {
            $message = $this->mailFromOverride($override, [':client' => $this->clientName, ':count' => (string) $count]);
        } else {
            $message = (new MailMessage)
                ->subject(__(':client uploaded :count files', ['client' => $this->clientName, 'count' => $count]))
                ->line(__(':client uploaded these :count files:', ['client' => $this->clientName, 'count' => $count]));
        }

        $names = array_map(fn (PendingNotification $item): ?string => $this->locationNameOf($item), $this->items);
        // One line for the whole burst when every file went to the same
        // place, which is nearly always; otherwise each file says where.
        $sameLocation = count(array_unique($names)) === 1 && $names[0] !== null;

        foreach ($this->items as $i => $item) {
            $message->line(__('File: :name', ['name' => $item->subject_name]));
            if (! $sameLocation && $names[$i] !== null) {
                $message->line(__('Stored in: :location', ['location' => $names[$i]]));
            }
        }

        if ($sameLocation) {
            $message->line(__('Stored in: :location', ['location' => $names[0]]));
        }

        // The library filtered to this client's uploads: every file in the
        // list, in one place, behind the usual sign-in.
        return $message->action(__('View the files'), route('files.index', ['uploader' => $this->clientId]));
    }

    private function locationNameOf(PendingNotification $item): ?string
    {
        $disk = $item->context['disk'] ?? null;

        return is_string($disk) ? ($this->locationNames[$disk] ?? null) : null;
    }
}
