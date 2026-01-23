<?php

use Livewire\Component;

new class extends Component
{
    public array $categoryItems;

    public function mount() {
        $this->categoryItems = [
            ['name' => 'Kablolar', 'image' => asset('images/icons/category-placeholder.svg'), 'href' => '#'],
            ['name' => 'Powerbank', 'image' => asset('images/icons/category-placeholder.svg'), 'href' => '#'],
            ['name' => 'Kulaklık', 'image' => asset('images/icons/category-placeholder.svg'), 'href' => '#'],
            ['name' => 'Telefon Kılıfı', 'image' => asset('images/icons/category-placeholder.svg'), 'href' => '#'],
            ['name' => 'Şarj Aleti', 'image' => asset('images/icons/category-placeholder.svg'), 'href' => '#'],
            ['name' => 'Aksesuar', 'image' => asset('images/icons/category-placeholder.svg'), 'href' => '#'],
            ['name' => 'Akse1suar', 'image' => asset('images/icons/category-placeholder.svg'), 'href' => '#'],
            ['name' => 'Akse2suar', 'image' => asset('images/icons/category-placeholder.svg'), 'href' => '#'],
            ['name' => 'Akses3uar', 'image' => asset('images/icons/category-placeholder.svg'), 'href' => '#'],
            ['name' => 'Akse4suar', 'image' => asset('images/icons/category-placeholder.svg'), 'href' => '#'],
            ['name' => 'Akse5suar', 'image' => asset('images/icons/category-placeholder.svg'), 'href' => '#'],
            ['name' => 'Oyun', 'image' => asset('images/icons/category-placeholder.svg'), 'href' => '#'],
            ['name' => 'Hediye Kartı', 'image' => asset('images/icons/category-placeholder.svg'), 'href' => '#'],
            ['name' => 'Premium', 'image' => asset('images/icons/category-placeholder.svg'), 'href' => '#'],
            ['name' => 'Yeni Ürün', 'image' => asset('images/icons/category-placeholder.svg'), 'href' => '#'],
            ['name' => 'Y1eni Ürün', 'image' => asset('images/icons/category-placeholder.svg'), 'href' => '#'],
            ['name' => 'Yeni2 Ürün', 'image' => asset('images/icons/category-placeholder.svg'), 'href' => '#'],
            ['name' => 'Yen3i Ürün', 'image' => asset('images/icons/category-placeholder.svg'), 'href' => '#'],
            ['name' => 'Yeni4 Ürün', 'image' => asset('images/icons/category-placeholder.svg'), 'href' => '#'],
        ];
    }
};
?>

<div
    x-data="{
        index: 0,
        total: {{ count($categoryItems) }},
        perView: 1,
        itemWidth: 0,
        gap: 0,
        intervalMs: 4200,
        timer: null,
        paused: false,
        reducedMotion: false,
        mediaQuery: null,
        motionHandler: null,
        resizeHandler: null,
        // Touch/Swipe properties
        isDragging: false,
        hasMoved: false,
        startX: 0,
        currentX: 0,
        dragOffset: 0,
        startTime: 0,
        velocityThreshold: 0.3,
        dragThreshold: 10,
        isTransitioning: false,
        init() {
            this.mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
            this.reducedMotion = this.mediaQuery.matches;
            this.motionHandler = (event) => {
                this.reducedMotion = event.matches;
                if (this.reducedMotion) {
                    this.stopAutoplay();
                } else {
                    this.startAutoplay();
                }
            };
            if (this.mediaQuery.addEventListener) {
                this.mediaQuery.addEventListener('change', this.motionHandler);
            } else if (this.mediaQuery.addListener) {
                this.mediaQuery.addListener(this.motionHandler);
            }
            this.$nextTick(() => {
                this.updateMeasurements();
                this.resizeHandler = () => this.updateMeasurements();
                window.addEventListener('resize', this.resizeHandler);
                this.startAutoplay();
            });
        },
        destroy() {
            this.stopAutoplay();
            if (this.resizeHandler) {
                window.removeEventListener('resize', this.resizeHandler);
            }
            if (this.mediaQuery) {
                if (this.mediaQuery.removeEventListener) {
                    this.mediaQuery.removeEventListener('change', this.motionHandler);
                } else if (this.mediaQuery.removeListener) {
                    this.mediaQuery.removeListener(this.motionHandler);
                }
            }
        },
        updateMeasurements() {
            if (!this.$refs.viewport || !this.$refs.track) {
                return;
            }
            const firstItem = this.$refs.track.firstElementChild;
            if (!firstItem) {
                return;
            }
            const styles = window.getComputedStyle(this.$refs.track);
            const gapValue = parseFloat(styles.columnGap || styles.gap || '0');
            this.gap = Number.isNaN(gapValue) ? 0 : gapValue;
            this.itemWidth = firstItem.getBoundingClientRect().width;
            const stride = this.itemWidth + this.gap;
            this.perView = stride > 0
                ? Math.max(1, Math.floor((this.$refs.viewport.clientWidth + this.gap) / stride))
                : 1;
            if (!this.canSlide()) {
                this.index = 0;
                this.stopAutoplay();
            } else {
                this.index = Math.min(this.index, this.maxIndex());
                this.startAutoplay();
            }
        },
        maxIndex() {
            return Math.max(this.total - this.perView, 0);
        },
        canSlide() {
            return this.total > this.perView;
        },
        translateX() {
            const baseOffset = (this.itemWidth + this.gap) * this.index;
            return `translateX(-${baseOffset + this.dragOffset}px)`;
        },
        next() {
            if (!this.canSlide() || this.isTransitioning) {
                return;
            }
            this.index = this.index >= this.maxIndex() ? 0 : this.index + 1;
        },
        prev() {
            if (!this.canSlide() || this.isTransitioning) {
                return;
            }
            this.index = this.index <= 0 ? this.maxIndex() : this.index - 1;
        },
        // Touch/Swipe handlers
        handleDragStart(event) {
            if (!this.canSlide()) {
                return;
            }
            this.isDragging = true;
            this.isTransitioning = false;
            this.hasMoved = false;
            this.startX = this.getEventX(event);
            this.currentX = this.startX;
            this.dragOffset = 0;
            this.startTime = Date.now();
            this.pause();
        },
        handleDragMove(event) {
            if (!this.isDragging) {
                return;
            }
            this.currentX = this.getEventX(event);
            const diff = this.startX - this.currentX;

            // Only prevent default if we've moved past the threshold
            if (Math.abs(diff) > this.dragThreshold) {
                event.preventDefault();
                this.hasMoved = true;
                this.dragOffset = diff;
            }
        },
        handleDragEnd(event) {
            if (!this.isDragging) {
                return;
            }

            const diff = this.startX - this.currentX;
            const timeDiff = Date.now() - this.startTime;
            const velocity = Math.abs(diff) / timeDiff;

            // Determine if we should slide to next/prev
            const minSwipeDistance = this.itemWidth * 0.2;
            const shouldNavigate = Math.abs(diff) > minSwipeDistance || velocity > this.velocityThreshold;

            this.isDragging = false;
            this.isTransitioning = true;

            if (shouldNavigate) {
                // Calculate the target index based on swipe direction
                let targetIndex = this.index;

                if (diff > 0) {
                    // Swiped left - go to next
                    targetIndex = this.index >= this.maxIndex() ? 0 : this.index + 1;
                } else {
                    // Swiped right - go to prev
                    targetIndex = this.index <= 0 ? this.maxIndex() : this.index - 1;
                }

                // Update index and reset dragOffset simultaneously
                // This ensures smooth transition from current visual position to target position
                this.index = targetIndex;
                this.dragOffset = 0;
            } else {
                // Snap back to current position - reset offset to animate back
                this.dragOffset = 0;
            }

            // Resume autoplay after transition completes
            setTimeout(() => {
                this.isTransitioning = false;
                this.resume();
            }, 500);
        },
        getEventX(event) {
            return event.type.includes('mouse') ? event.clientX : event.touches[0].clientX;
        },
        startAutoplay() {
            if (this.timer || this.reducedMotion || !this.canSlide()) {
                return;
            }
            this.timer = setInterval(() => {
                if (!this.paused && !this.isDragging) {
                    this.next();
                }
            }, this.intervalMs);
        },
        stopAutoplay() {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
        },
        pause() {
            this.paused = true;
        },
        resume() {
            this.paused = false;
        }
    }"
    x-init="init()"
    x-on:mouseenter="pause()"
    x-on:mouseleave="resume()"
    x-on:focusin="pause()"
    x-on:focusout="resume()"
    class="relative"
    role="region"
    aria-roledescription="carousel"
    aria-label="Kategori slider"
>
    <div class="relative">
{{--        <button--}}
{{--            type="button"--}}
{{--            class="absolute left-0 top-1/2 z-10 hidden h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full border border-(--color-zinc-200) bg-(--color-zinc-100) text-(--color-zinc-700) shadow-sm transition hover:bg-(--color-zinc-200) focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent) disabled:cursor-not-allowed disabled:opacity-40 dark:border-(--color-zinc-700) dark:bg-(--color-zinc-900) dark:text-(--color-zinc-200) dark:hover:bg-(--color-zinc-800) sm:flex"--}}
{{--            x-on:click="prev()"--}}
{{--            x-bind:disabled="!canSlide() || index === 0"--}}
{{--            aria-label="Önceki kategoriler"--}}
{{--        >--}}
{{--            <flux:icon icon="chevron-left" class="size-5" />--}}
{{--        </button>--}}

        <div
            class="overflow-hidden px-2 sm:px-0 pb-2 sm:pb-4 pt-4 sm:pt-8 cursor-grab select-none touch-pan-y"
            x-ref="viewport"
            x-on:mousedown="handleDragStart($event)"
            x-on:mousemove="handleDragMove($event)"
            x-on:mouseup="handleDragEnd($event)"
            x-on:mouseleave="isDragging && handleDragEnd($event)"
            x-on:touchstart.passive="handleDragStart($event)"
            x-on:touchmove="handleDragMove($event)"
            x-on:touchend.passive="handleDragEnd($event)"
            x-on:touchcancel.passive="handleDragEnd($event)"
            x-bind:class="{ 'cursor-grabbing': isDragging }"
        >
            <div
                class="flex items-start gap-0 sm:gap-2 motion-reduce:transition-none"
                x-ref="track"
                x-bind:style="`transform: ${translateX()};`"
                x-bind:class="{ 'transition-transform duration-500': !isDragging, 'transition-none': isDragging }"
            >
                @foreach ($categoryItems as $item)
                    <a
                        href="{{ $item['href'] }}"
                        class="group flex w-20 shrink-0 flex-col items-center gap-2 text-center select-none
                        focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent) sm:w-24"
                        x-on:click="if (hasMoved) { $event.preventDefault(); }"
                        draggable="false"
                    >
                        <span
                            class="flex size-16 items-center justify-center rounded-full border border-zinc-200
                             bg-white text-zinc-700 shadow-sm transition duration-200 group-hover:-translate-y-0.5
                              group-hover:border-accent group-hover:bg-zinc-50 group-hover:shadow-md
                               dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200
                               dark:group-hover:border-accent dark:group-hover:bg-zinc-700/40 sm:size-20"
                        >
                            <img
                                src="{{ $item['image'] }}"
                                alt="{{ $item['name'] }}"
                                class="h-8 w-8 object-contain sm:h-10 sm:w-10 pointer-events-none"
                                width="40"
                                height="40"
                                loading="lazy"
                                decoding="async"
                                draggable="false"
                            />
                        </span>
                    </a>
                @endforeach
            </div>
        </div>

{{--        <button--}}
{{--            type="button"--}}
{{--            class="absolute right-0 top-1/2 z-10 hidden h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full border border-(--color-zinc-200) bg-(--color-zinc-100) text-(--color-zinc-700) shadow-sm transition hover:bg-(--color-zinc-200) focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent) disabled:cursor-not-allowed disabled:opacity-40 dark:border-(--color-zinc-700) dark:bg-(--color-zinc-900) dark:text-(--color-zinc-200) dark:hover:bg-(--color-zinc-800) sm:flex"--}}
{{--            x-on:click="next()"--}}
{{--            x-bind:disabled="!canSlide() || index >= maxIndex()"--}}
{{--            aria-label="Sonraki kategoriler"--}}
{{--        >--}}
{{--            <flux:icon icon="chevron-right" class="size-5" />--}}
{{--        </button>--}}
    </div>
</div>
