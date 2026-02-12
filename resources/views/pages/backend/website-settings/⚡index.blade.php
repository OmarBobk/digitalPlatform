<?php

use App\Models\WebsiteSetting;
use Illuminate\Contracts\View\View;
use Livewire\Component;

new class extends Component
{
    private const ALLOWED_COUNTRY_CODES = ['+90', '+963'];

    public string $contactEmail = '';

    public ?string $primaryCountryCode = null;

    public string $primaryPhone = '';

    public ?string $secondaryCountryCode = null;

    public string $secondaryPhone = '';

    public bool $pricesVisible = true;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
        $settings = WebsiteSetting::instance();
        $this->contactEmail = $settings->contact_email ?? '';
        [$this->primaryCountryCode, $this->primaryPhone] = $this->parsePhone($settings->primary_phone ?? '');
        [$this->secondaryCountryCode, $this->secondaryPhone] = $this->parsePhone($settings->secondary_phone ?? '');
        $this->pricesVisible = (bool) $settings->prices_visible;
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private function parsePhone(string $full): array
    {
        if ($full === '') {
            return [null, ''];
        }
        foreach (self::ALLOWED_COUNTRY_CODES as $code) {
            if (str_starts_with($full, $code)) {
                $local = trim(substr($full, strlen($code)));

                return [in_array($code, self::ALLOWED_COUNTRY_CODES, true) ? $code : null, $local];
            }
        }

        return [null, $full];
    }

    private function formatPhoneForStorage(?string $countryCode, string $local): ?string
    {
        $local = trim($local);
        if ($countryCode !== null && $countryCode !== '' && $local !== '') {
            return $countryCode.' '.$local;
        }
        if ($local !== '') {
            return $local;
        }

        return null;
    }

    public function save(): void
    {
        $this->validate([
            'contactEmail' => ['nullable', 'email', 'max:255'],
            'primaryCountryCode' => ['nullable', 'string', 'in:+90,+963'],
            'primaryPhone' => ['nullable', 'string', 'max:30'],
            'secondaryCountryCode' => ['nullable', 'string', 'in:+90,+963'],
            'secondaryPhone' => ['nullable', 'string', 'max:30'],
        ]);

        WebsiteSetting::instance()->update([
            'contact_email' => $this->contactEmail !== '' ? $this->contactEmail : null,
            'primary_phone' => $this->formatPhoneForStorage($this->primaryCountryCode, $this->primaryPhone),
            'secondary_phone' => $this->formatPhoneForStorage($this->secondaryCountryCode, $this->secondaryPhone),
            'prices_visible' => $this->pricesVisible,
        ]);

        $this->dispatch('website-settings-saved');
    }

    public function render(): View
    {
        return $this->view()->title(__('messages.website_settings'));
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-6" data-test="website-settings-page">
    <section class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 sm:p-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <div class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">
                    <flux:icon icon="globe-alt" class="size-8" />
                </div>
                <div>
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                        {{ __('messages.website_settings') }}
                    </flux:heading>
                    <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">
                        {{ __('messages.website_settings_intro') }}
                    </flux:text>
                </div>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 sm:p-6">
        <form wire:submit="save" class="space-y-6">
            <flux:field>
                <flux:label>{{ __('messages.website_contact_email') }}</flux:label>
                <flux:input wire:model.defer="contactEmail" type="email" class="w-full max-w-md" class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0" />
                <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('messages.website_contact_email_hint') }}</flux:text>
                <flux:error name="contactEmail" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('messages.website_primary_phone') }}</flux:label>
                <flux:input.group>
                    <flux:select wire:model.defer="primaryCountryCode" class="max-w-fit focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0">
                        <flux:select.option value="">{{ __('messages.select_country_code') }}</flux:select.option>
                        <flux:select.option value="+90">+90</flux:select.option>
                        <flux:select.option value="+963">+963</flux:select.option>
                    </flux:select>
                    <flux:input wire:model.defer="primaryPhone" type="text" autocomplete="tel"
                                mask="(999) 999-9999"
                                placeholder="( ___ ) ___-____"
                                class="w-full" class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0" />
                </flux:input.group>
                <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('messages.website_primary_phone_hint') }}</flux:text>
                <flux:error name="primaryPhone" />
                <flux:error name="primaryCountryCode" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('messages.website_secondary_phone') }}</flux:label>
                <flux:input.group>
                    <flux:select wire:model.defer="secondaryCountryCode" class="max-w-fit focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0">
                        <flux:select.option value="">{{ __('messages.select_country_code') }}</flux:select.option>
                        <flux:select.option value="+90">+90</flux:select.option>
                        <flux:select.option value="+963">+963</flux:select.option>
                    </flux:select>
                    <flux:input wire:model.defer="secondaryPhone" type="text" autocomplete="tel"
                                mask="(999) 999-9999"
                                placeholder="( ___ ) ___-____"
                                class="w-full" class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0" />
                </flux:input.group>
                <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('messages.website_secondary_phone_hint') }}</flux:text>
                <flux:error name="secondaryPhone" />
                <flux:error name="secondaryCountryCode" />
            </flux:field>
            <flux:field>
                <div class="flex items-center gap-3">
                    <flux:label class="!mb-0">{{ __('messages.website_prices_visible') }}</flux:label>
                    <flux:switch wire:model.defer="pricesVisible" class="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0" />
                </div>
                <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('messages.website_prices_visible_hint') }}</flux:text>
            </flux:field>
            <div class="flex flex-wrap items-center gap-2">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    {{ __('messages.save') }}
                </flux:button>
                <x-action-message class="ms-2" on="website-settings-saved">{{ __('messages.saved') }}</x-action-message>
            </div>
        </form>
    </section>
</div>
