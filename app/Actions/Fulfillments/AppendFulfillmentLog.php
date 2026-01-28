<?php

declare(strict_types=1);

namespace App\Actions\Fulfillments;

use App\Enums\FulfillmentLogLevel;
use App\Models\Fulfillment;
use App\Models\FulfillmentLog;

class AppendFulfillmentLog
{
    /**
     * Append an audit/debug entry for a fulfillment.
     */
    public function handle(Fulfillment $fulfillment, FulfillmentLogLevel|string $level, string $message, array $context = []): FulfillmentLog
    {
        $resolvedLevel = $level instanceof FulfillmentLogLevel
            ? $level
            : FulfillmentLogLevel::from($level);

        return $fulfillment->logs()->create([
            'level' => $resolvedLevel,
            'message' => $message,
            'context' => $context !== [] ? $context : null,
        ]);
    }
}
