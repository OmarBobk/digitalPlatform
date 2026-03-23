<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\BugInboxChanged;
use App\Models\Bug;
use App\Notifications\BugRecordedNotification;
use App\Services\NotificationRecipientService;

final class SendBugRecordedAdminNotifications
{
    public function __construct(
        private NotificationRecipientService $notificationRecipientService
    ) {}

    public function __invoke(BugInboxChanged $event): void
    {
        if ($event->reason !== 'created' || $event->bugId === null) {
            return;
        }

        $bug = Bug::query()->find($event->bugId);
        if ($bug === null) {
            return;
        }

        $notification = BugRecordedNotification::fromBug($bug);

        $this->notificationRecipientService
            ->adminUsers()
            ->reject(fn ($admin) => (int) $admin->id === (int) $bug->user_id)
            ->each(fn ($admin) => $admin->notify($notification));
    }
}
