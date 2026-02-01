<?php

use App\Models\Package;
use Livewire\Component;

new class extends Component
{
    public array $categoryItems = [];

    public function mount(): void
    {
        $placeholderImage = asset('images/icons/category-placeholder.svg');

        $this->categoryItems = Package::query()
            ->select(['id', 'name', 'image', 'order'])
            ->where('is_active', true)
            ->orderBy('order')
            ->orderBy('name')
            ->limit(19)
            ->get()
            ->map(fn (Package $package): array => [
                'name' => $package->name,
                'image' => filled($package->image) ? asset($package->image) : $placeholderImage,
                'href' => '#',
            ])
            ->all();
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
        isRtl: false,
        rtlScrollType: 'reverse',
        reducedMotion: false,
        mediaQuery: null,
        motionHandler: null,
        resizeHandler: null,
        isPointerDown: false,
        hasMoved: false,
        startX: 0,
        startScroll: 0,
        dragThreshold: 2,
        scrollTimeout: null,
        rafId: null,
        init() {
            this.mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
            this.reducedMotion = this.mediaQuery.matches;
            this.isRtl = this.getDirection() === 'rtl';
            this.rtlScrollType = this.isRtl ? this.getRtlScrollType() : 'ltr';
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
        getDirection() {
            const root = this.$root.closest('[dir]');
            return root?.getAttribute('dir') ?? document.documentElement.getAttribute('dir') ?? 'ltr';
        },
        getRtlScrollType() {
            const el = this.$refs.viewport;
            if (!el) {
                return 'reverse';
            }

            const initial = el.scrollLeft;
            el.scrollLeft = 1;
            const after = el.scrollLeft;
            el.scrollLeft = initial;

            if (after === 0) {
                return 'negative';
            }

            return initial === 0 ? 'reverse' : 'default';
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
            this.scrollToIndex(this.index, 'auto');
        },
        maxIndex() {
            return Math.max(this.total - this.perView, 0);
        },
        canSlide() {
            return this.total > this.perView;
        },
        getLogicalScroll() {
            const el = this.$refs.viewport;
            if (!el) {
                return 0;
            }

            const max = Math.max(el.scrollWidth - el.clientWidth, 0);

            if (!this.isRtl) {
                return Math.max(0, Math.min(el.scrollLeft, max));
            }

            const raw = el.scrollLeft;

            if (this.rtlScrollType === 'negative') {
                return Math.abs(raw);
            }

            if (this.rtlScrollType === 'default') {
                return max - raw;
            }

            return raw;
        },
        scrollToLogical(position, behavior = 'smooth') {
            const el = this.$refs.viewport;
            if (!el) {
                return;
            }

            const max = Math.max(el.scrollWidth - el.clientWidth, 0);
            const clamped = Math.max(0, Math.min(position, max));
            let left = clamped;

            if (this.isRtl) {
                if (this.rtlScrollType === 'negative') {
                    left = -clamped;
                } else if (this.rtlScrollType === 'default') {
                    left = max - clamped;
                }
            }

            el.scrollTo({
                left,
                behavior: this.reducedMotion ? 'auto' : behavior,
            });
        },
        scrollToIndex(index, behavior = 'smooth') {
            if (!this.canSlide()) {
                return;
            }

            const stride = this.itemWidth + this.gap;
            const offset = stride * index;
            this.scrollToLogical(offset, behavior);
        },
        next() {
            if (!this.canSlide()) {
                return;
            }
            this.index = this.index >= this.maxIndex() ? 0 : this.index + 1;
            this.scrollToIndex(this.index);
        },
        prev() {
            if (!this.canSlide()) {
                return;
            }
            this.index = this.index <= 0 ? this.maxIndex() : this.index - 1;
            this.scrollToIndex(this.index);
        },
        handleScroll() {
            if (!this.canSlide()) {
                return;
            }

            this.pause();
            if (this.scrollTimeout) {
                clearTimeout(this.scrollTimeout);
            }
            this.scrollTimeout = setTimeout(() => this.resume(), 800);

            if (this.rafId) {
                return;
            }

            this.rafId = requestAnimationFrame(() => {
                this.rafId = null;
                const stride = this.itemWidth + this.gap;
                if (stride <= 0) {
                    return;
                }
                const position = this.getLogicalScroll();
                const nextIndex = Math.min(this.maxIndex(), Math.max(0, Math.round(position / stride)));
                this.index = nextIndex;
            });
        },
        handlePointerDown(event) {
            if (event.pointerType && event.pointerType !== 'mouse') {
                return;
            }
            if (event.button !== undefined && event.button !== 0) {
                return;
            }
            if (!this.canSlide()) {
                return;
            }
            this.isPointerDown = true;
            this.hasMoved = false;
            this.startX = event.clientX;
            this.startScroll = this.getLogicalScroll();
            this.pause();
        },
        handlePointerMove(event) {
            if (event.pointerType && event.pointerType !== 'mouse') {
                return;
            }
            if (!this.isPointerDown) {
                return;
            }
            const delta = event.clientX - this.startX;
            if (!this.hasMoved && Math.abs(delta) > this.dragThreshold) {
                this.hasMoved = true;
            }
            if (this.hasMoved) {
                event.preventDefault();
            }
            this.scrollToLogical(this.startScroll - delta, 'auto');
        },
        handlePointerUp() {
            if (!this.isPointerDown) {
                return;
            }
            this.isPointerDown = false;
            setTimeout(() => {
                this.hasMoved = false;
            }, 120);
            if (this.scrollTimeout) {
                clearTimeout(this.scrollTimeout);
            }
            this.scrollTimeout = setTimeout(() => this.resume(), 800);
        },
        startAutoplay() {
            if (this.timer || this.reducedMotion || !this.canSlide()) {
                return;
            }
            this.timer = setInterval(() => {
                if (!this.paused) {
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
    aria-label="{{ __('main.category_slider') }}"
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
            class="overflow-x-auto scrollbar-hide !px-2 sm:px-0 pb-2 sm:pb-4 pt-4 sm:pt-8 select-none scroll-smooth cursor-grab"
            x-ref="viewport"
            x-on:scroll.passive="handleScroll()"
            x-on:pointerdown="handlePointerDown($event)"
            x-on:pointermove="handlePointerMove($event)"
            x-on:pointerup="handlePointerUp()"
            x-on:pointercancel="handlePointerUp()"
            x-on:pointerleave="handlePointerUp()"
            x-bind:class="{ 'cursor-grabbing': isPointerDown }"
        >
            <div
                class="flex items-start gap-4 motion-reduce:transition-none"
                x-ref="track"
            >
                @foreach ($categoryItems as $item)
                    <a
                        href="{{ $item['href'] }}"
                        class="group flex shrink-0 flex-col items-center gap-2 text-center select-none
                        focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent)"
                        x-on:click="if (hasMoved) { $event.preventDefault(); }"
                        draggable="false"
                    >
                        <div
                            class="w-full h-full rounded-full flex items-center justify-center border border-zinc-200
                             bg-white text-zinc-700 shadow-sm transition duration-200 group-hover:-translate-y-0.5
                              group-hover:border-accent group-hover:bg-zinc-50 group-hover:shadow-md
                               dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200
                               dark:group-hover:border-accent dark:group-hover:bg-zinc-700/40"
                        >
                            <img
                                src="{{ $item['image'] }}"
                                alt="{{ $item['name'] }}"
                                class="h-16 w-16 rounded-full object-contain sm:h-auto sm:w-16 pointer-events-none transition duration-200 group-hover:transform group-hover:scale-[1.2]"
                                loading="lazy"
                                decoding="async"
                                draggable="false"
                            />
                        </div>
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
