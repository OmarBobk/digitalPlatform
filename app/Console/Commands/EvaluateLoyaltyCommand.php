<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Loyalty\EvaluateLoyaltyForUserAction;
use App\Models\User;
use Illuminate\Console\Command;

class EvaluateLoyaltyCommand extends Command
{
    protected $signature = 'loyalty:evaluate
                            {user_id? : Optional user ID to evaluate; if omitted, all non-locked users are evaluated}';

    protected $description = 'Evaluate loyalty tier from rolling spend (idempotent)';

    public function handle(EvaluateLoyaltyForUserAction $action): int
    {
        $userId = $this->argument('user_id');

        if ($userId !== null && $userId !== '') {
            $id = (int) $userId;
            $user = User::query()->find($id);
            if ($user === null) {
                $this->error("User not found: {$userId}");

                return self::FAILURE;
            }
            if ($action->handle($user)) {
                $this->info("Evaluated loyalty for user {$id}.");
            } else {
                $this->warn("User {$id} is locked; skipped.");
            }

            return self::SUCCESS;
        }

        $users = User::query()->get();
        $count = 0;
        foreach ($users as $user) {
            if ($action->handle($user)) {
                $count++;
            }
        }

        $this->info("Evaluated loyalty for {$count} user(s).");

        return self::SUCCESS;
    }
}
