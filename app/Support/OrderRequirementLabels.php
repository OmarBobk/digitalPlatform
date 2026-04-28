<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\OrderItem;
use Illuminate\Support\Facades\Lang;

final class OrderRequirementLabels
{
    public static function labelForKey(?OrderItem $item, string $key): string
    {
        $fromPackage = self::labelFromPackageRequirements($item, $key);

        if ($fromPackage !== null && $fromPackage !== '') {
            return $fromPackage;
        }

        return self::fallbackLabel($key);
    }

    private static function labelFromPackageRequirements(?OrderItem $item, string $key): ?string
    {
        if ($item === null) {
            return null;
        }

        if (! $item->relationLoaded('package')) {
            return null;
        }

        $package = $item->package;
        if ($package === null || ! $package->relationLoaded('requirements')) {
            return null;
        }

        $requirement = $package->requirements->firstWhere('key', $key);
        if ($requirement === null) {
            return null;
        }

        $label = trim((string) $requirement->label);

        return $label !== '' ? $label : null;
    }

    public static function fallbackLabel(string $key): string
    {
        $translationKey = 'messages.requirement_key_'.$key;
        if (Lang::has($translationKey)) {
            return __($translationKey);
        }

        return match (strtolower($key)) {
            'id' => __('messages.requirement_label_id'),
            'email' => __('messages.requirement_label_email'),
            'username' => __('messages.requirement_label_username'),
            'password' => __('messages.requirement_label_password'),
            'phone' => __('messages.requirement_label_phone'),
            'notes' => __('messages.requirement_label_notes'),
            default => $key,
        };
    }
}
