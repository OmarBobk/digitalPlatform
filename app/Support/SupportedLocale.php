<?php

namespace App\Support;

use Illuminate\Http\Request;

final class SupportedLocale
{
    /**
     * Locales with translation files and UI support.
     *
     * @var list<string>
     */
    public const ALLOWED = ['en', 'ar'];

    /**
     * Resolve the best supported locale from the request Accept-Language header.
     * When the header is missing or does not match, falls back to the primary app locale.
     */
    public static function fromRequest(Request $request): string
    {
        $order = self::preferredLanguageOrder();
        $resolved = $request->getPreferredLanguage($order);

        return in_array($resolved, self::ALLOWED, true) ? $resolved : $order[0];
    }

    /**
     * @return list<string>
     */
    public static function preferredLanguageOrder(): array
    {
        $primary = config('app.locale', 'en');
        $primary = in_array($primary, self::ALLOWED, true) ? $primary : 'en';
        $rest = array_values(array_diff(self::ALLOWED, [$primary]));

        return array_merge([$primary], $rest);
    }
}
