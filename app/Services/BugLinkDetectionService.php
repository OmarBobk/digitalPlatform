<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\TopupRequest;
use Illuminate\Http\Request;

class BugLinkDetectionService
{
    /**
     * @return array<int, array{type: string, reference_id: int}>
     */
    public function detect(Request $request): array
    {
        $links = [];
        $parameters = $request->route()?->parameters() ?? [];

        $order = $parameters['order'] ?? null;
        $orderId = $this->resolveModelId($order, Order::class);
        if ($orderId !== null) {
            $links[] = ['type' => 'order', 'reference_id' => $orderId];
        }

        $fulfillment = $parameters['fulfillment'] ?? null;
        $fulfillmentId = $this->resolveModelId($fulfillment, Fulfillment::class);
        if ($fulfillmentId !== null) {
            $links[] = ['type' => 'fulfillment', 'reference_id' => $fulfillmentId];
        }

        $topup = $parameters['topup'] ?? $parameters['topupRequest'] ?? null;
        $topupId = $this->resolveModelId($topup, TopupRequest::class);
        if ($topupId !== null) {
            $links[] = ['type' => 'topup', 'reference_id' => $topupId];
        }

        $notificationId = $parameters['notification'] ?? null;
        if (is_numeric($notificationId)) {
            $links[] = ['type' => 'notification', 'reference_id' => (int) $notificationId];
        }

        return collect($links)->unique(fn (array $link) => $link['type'].'-'.$link['reference_id'])->values()->all();
    }

    private function resolveModelId(mixed $value, string $class): ?int
    {
        if ($value instanceof $class) {
            return (int) $value->getKey();
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }
}
