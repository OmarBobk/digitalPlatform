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
     * Resolves related entities for a bug report. Livewire submits use the `/livewire/update`
     * route, so {@see detect} alone rarely sees route parameters; use {@see detectForSubmit}
     * with the browser page URL and optional client `last_pages` from metadata.
     *
     * @param  array<int, array<string, mixed>>  $lastPages
     * @param  array<int, mixed>  $lastNotifications
     * @return array<int, array{type: string, reference_id: int}>
     */
    public function detectForSubmit(
        Request $request,
        string $pageUrl,
        array $lastPages = [],
        array $lastNotifications = []
    ): array {
        $links = $this->detect($request);

        $urls = array_filter([$pageUrl]);
        foreach ($lastPages as $entry) {
            if (is_array($entry) && isset($entry['url']) && is_string($entry['url']) && $entry['url'] !== '') {
                $urls[] = $entry['url'];
            }
        }

        foreach ($urls as $url) {
            $links = array_merge($links, $this->detectFromUrl($url));
        }

        foreach ($lastNotifications as $notification) {
            if (! is_array($notification)) {
                continue;
            }
            $id = $notification['id'] ?? null;
            if (is_numeric($id)) {
                $links[] = ['type' => 'notification', 'reference_id' => (int) $id];
            }
        }

        return collect($links)
            ->unique(fn (array $link) => $link['type'].'-'.$link['reference_id'])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{type: string, reference_id: int}>
     */
    public function detectFromUrl(string $url): array
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return [];
        }

        $path = '/'.trim($path, '/');

        $links = [];

        if (preg_match('#^/admin/orders/(\d+)(?:/|$)#', $path, $m)) {
            $links[] = ['type' => 'order', 'reference_id' => (int) $m[1]];
        }

        if (preg_match('#^/orders/([^/]+)(?:/|$)#', $path, $m)) {
            $orderId = $this->resolveOrderIdFromPathSegment((string) $m[1]);
            if ($orderId !== null) {
                $links[] = ['type' => 'order', 'reference_id' => $orderId];
            }
        }

        return $links;
    }

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

    private function resolveOrderIdFromPathSegment(string $segment): ?int
    {
        $segment = trim($segment);
        if ($segment === '') {
            return null;
        }

        $byNumber = Order::query()->where('order_number', $segment)->value('id');
        if ($byNumber !== null) {
            return (int) $byNumber;
        }

        if (ctype_digit($segment)) {
            $byId = Order::query()->whereKey((int) $segment)->value('id');
            if ($byId !== null) {
                return (int) $byId;
            }
        }

        return null;
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
