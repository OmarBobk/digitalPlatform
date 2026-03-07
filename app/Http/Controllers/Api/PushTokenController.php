<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushTokenController extends Controller
{
    /**
     * Register or update FCM token for the authenticated admin.
     */
    public function register(Request $request): JsonResponse
    {
        if (! $request->user()?->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'fcm_token' => ['required', 'string', 'max:500'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        AdminDevice::query()->updateOrCreate(
            ['fcm_token' => $validated['fcm_token']],
            [
                'user_id' => $request->user()->id,
                'device_name' => $validated['device_name'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        return response()->json(['ok' => true]);
    }
}
