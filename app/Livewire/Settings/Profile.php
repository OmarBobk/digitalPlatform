<?php

namespace App\Livewire\Settings;

use App\Concerns\ProfileValidationRules;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyTierConfig;
use App\Services\LoyaltySpendService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Masmerise\Toaster\Toastable;

class Profile extends Component
{
    use ProfileValidationRules;
    use Toastable;

    public string $name = '';

    public string $username = '';

    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->username = Auth::user()->username ?? '';
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => $this->nameRules(),
            'username' => $this->usernameRules($user->id),
            'email' => $this->emailRules($user->id),
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->success(__('messages.saved'));
        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();
        $this->info(__('messages.verification_link_sent'));
        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }

    #[Computed]
    public function loyaltyRollingSpend(): float
    {
        $windowDays = LoyaltySetting::getRollingWindowDays();

        return app(LoyaltySpendService::class)->computeRollingSpend(Auth::user(), $windowDays);
    }

    #[Computed]
    public function loyaltyCurrentTierConfig(): ?LoyaltyTierConfig
    {
        $user = Auth::user();
        $role = $user->loyaltyRole();
        if ($role === null) {
            return null;
        }
        $tierName = $user->loyalty_tier?->value ?? 'bronze';

        return LoyaltyTierConfig::query()->forRole($role)->where('name', $tierName)->first();
    }

    #[Computed]
    public function loyaltyNextTier(): ?LoyaltyTierConfig
    {
        $user = Auth::user();
        $role = $user->loyaltyRole();
        if ($role === null) {
            return null;
        }
        $spend = $this->loyaltyRollingSpend;

        return LoyaltyTierConfig::query()
            ->forRole($role)
            ->where('min_spend', '>', $spend)
            ->orderBy('min_spend')
            ->first();
    }

    /**
     * Progress (0–100) toward the next tier threshold. Null if at top tier.
     */
    #[Computed]
    public function loyaltyProgressPercent(): ?float
    {
        $next = $this->loyaltyNextTier;
        if ($next === null) {
            return null;
        }
        $spend = $this->loyaltyRollingSpend;
        $threshold = (float) $next->min_spend;
        if ($threshold <= 0) {
            return 100.0;
        }

        return min(100.0, round(($spend / $threshold) * 100, 1));
    }

    /**
     * Amount ($) left to spend to reach the next tier. Null if at top tier.
     */
    #[Computed]
    public function loyaltyAmountToNextTier(): ?float
    {
        $next = $this->loyaltyNextTier;
        if ($next === null) {
            return null;
        }

        return max(0.0, (float) $next->min_spend - $this->loyaltyRollingSpend);
    }

    public function render()
    {
        return view('livewire.settings.profile')
            ->title(__('messages.profile'));
    }
}
