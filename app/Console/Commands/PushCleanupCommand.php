<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AdminDevice;
use Illuminate\Console\Command;

class PushCleanupCommand extends Command
{
    protected $signature = 'push:cleanup
                            {--dry-run : List devices that would be deleted without deleting}';

    protected $description = 'Remove admin_devices not seen for 90 days';

    public function handle(): int
    {
        $cutoff = now()->subDays(90);

        $query = AdminDevice::query()->where(function ($q) use ($cutoff) {
            $q->where('last_seen_at', '<', $cutoff)
                ->orWhere(fn ($q2) => $q2->whereNull('last_seen_at')->where('created_at', '<', $cutoff));
        });

        $count = $query->count();

        if ($count === 0) {
            $this->components->info('No stale devices to remove.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->components->warn("Would delete {$count} device(s) (last_seen_at or created_at &lt; 90 days ago). Run without --dry-run to delete.");

            return self::SUCCESS;
        }

        $query->delete();
        $this->components->info("Deleted {$count} stale device(s).");

        return self::SUCCESS;
    }
}
