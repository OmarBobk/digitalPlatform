<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AdminDevice;
use App\Models\PushLog;
use App\Services\FirebasePushService;
use Illuminate\Console\Command;

class PushHealthCheckCommand extends Command
{
    protected $signature = 'push:health-check';

    protected $description = 'Report on Firebase push: credentials, queue, device count, last push log';

    public function handle(FirebasePushService $firebase): int
    {
        $firebaseOk = $firebase->hasValidCredentials();
        $deviceCount = AdminDevice::query()->count();
        $lastLog = PushLog::query()->latest('id')->first();

        $this->components->twoColumnDetail('Firebase credentials', $firebaseOk ? '<fg=green>OK</>' : '<fg=red>Invalid or missing</>');
        $this->components->twoColumnDetail('Queue worker', 'Run: <comment>php artisan queue:work --queue=push,default</comment>');
        $this->components->twoColumnDetail('admin_devices count', (string) $deviceCount);
        $this->components->twoColumnDetail(
            'Last push log',
            $lastLog
                ? sprintf('%s (%s)', $lastLog->status, $lastLog->created_at?->toDateTimeString() ?? '')
                : 'none'
        );

        if ($lastLog && $lastLog->error) {
            $this->newLine();
            $this->components->warn('Last error: '.$lastLog->error);
        }

        return $firebaseOk ? self::SUCCESS : self::FAILURE;
    }
}
