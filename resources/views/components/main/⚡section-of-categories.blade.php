<?php

declare(strict_types=1);

use Livewire\Component;

new class extends Component
{
    public array $giftCardItems;

    public function mount(): void
    {
        $this->giftCardItems = [
            [
                'name' => 'App Store',
                'label' => 'APP STORE',
                'href' => '#',
                'icon' => 'https://cdn.simpleicons.org/apple/ffffff',
            ],
            [
                'name' => 'PlayStation',
                'label' => 'PLAYSTATION',
                'href' => '#',
                'icon' => 'https://cdn.simpleicons.org/playstation/ffffff',
            ],
            [
                'name' => 'Steam',
                'label' => 'STEAM',
                'href' => '#',
                'icon' => 'https://cdn.simpleicons.org/steam/ffffff',
            ],
            [
                'name' => 'Google Play',
                'label' => 'GOOGLE PLAY',
                'href' => '#',
                'icon' => 'https://cdn.simpleicons.org/googleplay/ffffff',
            ],
            [
                'name' => 'Xbox',
                'label' => 'XBOX',
                'href' => '#',
                'icon' => 'https://cdn.simpleicons.org/xbox/ffffff',
            ],
            [
                'name' => 'Razer Gold',
                'label' => 'RAZER GOLD',
                'href' => '#',
                'icon' => 'https://cdn.simpleicons.org/razer/ffffff',
            ],
            [
                'name' => 'Amazon',
                'label' => 'AMAZON',
                'href' => '#',
                'icon' => 'https://cdn.simpleicons.org/amazon/ffffff',
            ],
            [
                'name' => 'Battle.net',
                'label' => 'BATTLENET',
                'href' => '#',
                'icon' => 'https://cdn.simpleicons.org/battledotnet/ffffff',
            ],
        ];
    }
};
?>

<div class="px-2 py-3 sm:px-0 sm:py-4">
    <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 sm:p-6">
        <div class="flex flex-col gap-4 sm:gap-5">
            <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                Hediye Kartları
            </flux:heading>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4 lg:grid-cols-8">
                @foreach ($giftCardItems as $item)
                    <a
                        href="{{ $item['href'] }}"
                        @class([
                            'group flex aspect-[4/3] flex-col items-center justify-center gap-3 rounded-xl border border-zinc-200 bg-white px-3 py-4 text-zinc-900 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-accent hover:bg-zinc-50 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent) dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:hover:border-accent dark:hover:bg-zinc-800/80',
                        ])
                        aria-label="{{ $item['name'] }} hediye kartı"
                    >
                        <span class="flex size-11 items-center justify-center rounded-full border border-zinc-200 bg-zinc-800 shadow-sm transition group-hover:border-accent dark:border-zinc-700 dark:bg-zinc-800">
                        <img
                            src="{{ $item['icon'] }}"
                            alt="{{ $item['name'] }} logo"
                            class="h-6 w-6 sm:h-7 sm:w-7"
                            width="28"
                            height="28"
                            loading="lazy"
                            decoding="async"
                        />
                        </span>
                        <span class="text-[0.65rem] font-semibold tracking-[0.22em] text-zinc-600 group-hover:text-zinc-900 dark:text-zinc-300 dark:group-hover:text-white">
                            {{ $item['label'] }}
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</div>
