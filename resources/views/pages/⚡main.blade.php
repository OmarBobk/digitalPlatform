<?php

use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::frontend')] class extends Component {

    public array $heroBanner;

    public function mount()
    {
        $this->heroBanner = [
            'image' => asset('images/promotions/hero-placeholder.svg'),
            'href' => '#',
        ];
    }

    public function render()
    {
        return $this->view()->title(__('main.homepage'));
    }
};
?>

<div>
    <section class="mx-auto w-full max-w-7xl" data-section="homepage-marquee">
        <livewire:landing.circular-slider />
    </section>

    <section class="mx-auto w-full max-w-7xl  pb-10 pt-2 sm:pt-4" data-section="homepage-promos">
        <div class="grid sm:gap-6 gap-4 sm:grid-cols-4">
            <div class="flex sm:flex-col sm:gap-4 gap-2 justify-between">
                <a href="{{ $heroBanner['href'] }}"
                   class="group block focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent)"
                   aria-label="Öne çıkan kampanya">
                    <div
                        class="sm:aspect-[16/9] aspect-[15/9] w-full overflow-hidden rounded-xl border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900">
                        <img src="{{ $heroBanner['image'] }}" alt="Öne çıkan kampanya banneri"
                             class="h-full w-full object-cover" width="960" height="600" loading="eager" fetchpriority="high"
                             decoding="async" />
                    </div>
                </a>
                <a href="{{ $heroBanner['href'] }}"
                   class="group block focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent)"
                   aria-label="Öne çıkan kampanya">
                    <div
                        class="sm:aspect-[16/9] aspect-[15/9] w-full overflow-hidden rounded-xl border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900">
                        <img src="{{ $heroBanner['image'] }}" alt="Öne çıkan kampanya banneri"
                             class="h-full w-full object-cover" width="960" height="600" loading="eager" fetchpriority="high"
                             decoding="async" />
                    </div>
                </a>
            </div>
            <livewire:landing.promotional-sliders />
        </div>
    </section>

    <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
        <flux:radio value="light" icon="sun">{{ __('messages.light') }}</flux:radio>
        <flux:radio value="dark" icon="moon">{{ __('messages.dark') }}</flux:radio>
        <flux:radio value="system" icon="computer-desktop">{{ __('messages.system') }}</flux:radio>
    </flux:radio.group>

    <livewire:language-switcher />
</div>
