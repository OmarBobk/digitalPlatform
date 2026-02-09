<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Loyalty\EvaluateLoyaltyForUserAction;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluateLoyaltyForUser implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $userId
    ) {}

    public function handle(EvaluateLoyaltyForUserAction $action): void
    {
        $user = User::query()->find($this->userId);

        if ($user !== null) {
            $action->handle($user);
        }
    }
}
