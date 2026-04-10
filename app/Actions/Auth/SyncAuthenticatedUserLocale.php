<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\SupportedLocale;
use Illuminate\Http\Request;

final class SyncAuthenticatedUserLocale
{
    /**
     * Align session and persisted locale after password login or two-factor completion.
     *
     * Preference order:
     * 1. Session locale when set (e.g. guest used the language switcher before signing in).
     * 2. Saved account locale when the user has locked a preference (language switcher).
     * 3. Accept-Language, persisted until the user switches language manually.
     */
    public function execute(Request $request, User $user): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $sessionLocale = $request->session()->get('locale');

        if (in_array($sessionLocale, SupportedLocale::ALLOWED, true)) {
            if ($user->locale !== $sessionLocale || ! $user->locale_manually_set) {
                $user->forceFill([
                    'locale' => $sessionLocale,
                    'locale_manually_set' => true,
                ])->save();
            }

            return;
        }

        if ($user->locale_manually_set) {
            $request->session()->put('locale', $user->preferredLocale());

            return;
        }

        $locale = SupportedLocale::fromRequest($request);
        $request->session()->put('locale', $locale);
        $user->forceFill(['locale' => $locale])->save();
    }
}
