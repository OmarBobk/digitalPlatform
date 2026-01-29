<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerAuthActivityHooks();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }

    protected function registerAuthActivityHooks(): void
    {
        Event::listen(Logout::class, function (Logout $event): void {
            $user = $event->user;

            if ($user === null) {
                return;
            }

            activity()
                ->inLog('admin')
                ->event('user.logout')
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties([
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'is_active' => $user->is_active,
                    'email_verified_at' => $user->email_verified_at?->format('M d, Y H:i') ?? '—',
                    'phone' => $user->phone,
                ])
                ->log('User logout');

            if ($user->hasAnyRole(['admin', 'supervisor'])) {
                activity()
                    ->inLog('admin')
                    ->event('admin.logout')
                    ->performedOn($user)
                    ->causedBy($user)
                    ->withProperties([
                        'user_id' => $user->id,
                        'username' => $user->username,
                        'email' => $user->email,
                        'is_active' => $user->is_active,
                        'email_verified_at' => $user->email_verified_at?->format('M d, Y H:i') ?? '—',
                        'phone' => $user->phone,
                    ])
                    ->log('Admin logout');
            }
        });
    }
}
