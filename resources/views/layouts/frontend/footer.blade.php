<footer class="mt-10 border-t border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
    <div class="mx-auto w-full max-w-7xl px-3 py-10 sm:py-12">
        <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 sm:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                        Haftalık fırsatlar
                    </flux:heading>
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-300">
                        Yeni indirimleri ve kuponları ilk sen öğren. Spam yok, sadece değerli fırsatlar.
                    </flux:text>
                </div>
                <div class="flex w-full flex-col gap-3 sm:flex-row sm:items-center lg:max-w-md" role="group" aria-label="Bülten aboneliği">
                    <flux:input
                        type="email"
                        name="email"
                        autocomplete="email"
                        inputmode="email"
                        placeholder="E-posta adresin"
                        class="w-full"
                        class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                        aria-label="E-posta adresi"
                    />
                    <flux:button variant="primary" class="shrink-0">
                        Katıl
                    </flux:button>
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-3">
            <div class="flex items-start gap-3 rounded-xl border border-zinc-200 bg-white px-3 py-2 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <span class="mt-0.5 flex size-8 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon icon="bolt" class="size-4 text-(--color-accent)" />
                </span>
                <div class="flex flex-col gap-0.5">
                    <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Hızlı teslimat</span>
                    <span class="text-xs text-zinc-600 dark:text-zinc-300">Dakikalar içinde kodlar.</span>
                </div>
            </div>
            <div class="flex items-start gap-3 rounded-xl border border-zinc-200 bg-white px-3 py-2 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <span class="mt-0.5 flex size-8 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon icon="shield-check" class="size-4 text-(--color-accent)" />
                </span>
                <div class="flex flex-col gap-0.5">
                    <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Güvenli ödeme</span>
                    <span class="text-xs text-zinc-600 dark:text-zinc-300">Korunan işlem altyapısı.</span>
                </div>
            </div>
            <div class="flex items-start gap-3 rounded-xl border border-zinc-200 bg-white px-3 py-2 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <span class="mt-0.5 flex size-8 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon icon="chat-bubble-left-right" class="size-4 text-(--color-accent)" />
                </span>
                <div class="flex flex-col gap-0.5">
                    <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">7/24 destek</span>
                    <span class="text-xs text-zinc-600 dark:text-zinc-300">Her zaman yanındayız.</span>
                </div>
            </div>
        </div>

        <div class="mt-8 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
            <div class="flex flex-col gap-5">
                <a
                    href="{{ route('home') }}"
                    class="inline-flex items-center gap-2 text-2xl font-bold leading-none text-zinc-900 transition hover:text-(--color-accent) focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent) dark:text-zinc-100"
                    aria-label="indirimGo ana sayfa"
                >
                    <span class="text-(--color-accent)">indirim</span><span>Go</span>
                </a>
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-300">
                    Dijital ürünlerde güvenli ve hızlı alışveriş. En iyi fiyatlar, anında teslimat.
                </flux:text>
            </div>
            <div class="flex flex-col gap-3">
                <flux:heading class="text-zinc-900 dark:text-zinc-100">Alışveriş</flux:heading>
                <ul class="flex flex-col gap-2 text-sm">
                    <li>
                        <a href="#" class="text-zinc-600 transition hover:text-zinc-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent) dark:text-zinc-300 dark:hover:text-white">İndirimler</a>
                    </li>
                    <li>
                        <a href="#" class="text-zinc-600 transition hover:text-zinc-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent) dark:text-zinc-300 dark:hover:text-white">Yeni ürünler</a>
                    </li>
                    <li>
                        <a href="#" class="text-zinc-600 transition hover:text-zinc-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent) dark:text-zinc-300 dark:hover:text-white">Popüler kategoriler</a>
                    </li>
                    <li>
                        <a href="#" class="text-zinc-600 transition hover:text-zinc-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent) dark:text-zinc-300 dark:hover:text-white">Hediye kartları</a>
                    </li>
                </ul>
            </div>
            <div class="flex flex-col gap-3">
                <flux:heading class="text-zinc-900 dark:text-zinc-100">Kurumsal</flux:heading>
                <ul class="flex flex-col gap-2 text-sm">
                    <li>
                        <a href="#" class="text-zinc-600 transition hover:text-zinc-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent) dark:text-zinc-300 dark:hover:text-white">Hakkımızda</a>
                    </li>
                    <li>
                        <a href="#" class="text-zinc-600 transition hover:text-zinc-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent) dark:text-zinc-300 dark:hover:text-white">Kariyer</a>
                    </li>
                    <li>
                        <a href="#" class="text-zinc-600 transition hover:text-zinc-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent) dark:text-zinc-300 dark:hover:text-white">İş ortaklığı</a>
                    </li>
                    <li>
                        <a href="#" class="text-zinc-600 transition hover:text-zinc-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent) dark:text-zinc-300 dark:hover:text-white">Basın kiti</a>
                    </li>
                </ul>
            </div>
            <div class="flex flex-col gap-3">
                <flux:heading class="text-zinc-900 dark:text-zinc-100">Yardım</flux:heading>
                <ul class="flex flex-col gap-2 text-sm">
                    <li>
                        <a href="#" class="text-zinc-600 transition hover:text-zinc-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent) dark:text-zinc-300 dark:hover:text-white">Sıkça sorulanlar</a>
                    </li>
                    <li>
                        <a href="#" class="text-zinc-600 transition hover:text-zinc-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent) dark:text-zinc-300 dark:hover:text-white">İade politikası</a>
                    </li>
                    <li>
                        <a href="#" class="text-zinc-600 transition hover:text-zinc-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent) dark:text-zinc-300 dark:hover:text-white">Sipariş takibi</a>
                    </li>
                    <li>
                        <a href="#" class="text-zinc-600 transition hover:text-zinc-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent) dark:text-zinc-300 dark:hover:text-white">Bize ulaşın</a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="mt-8 flex flex-col gap-4 border-t border-zinc-200 pt-6 text-sm text-zinc-600 dark:border-zinc-800 dark:text-zinc-300 sm:flex-row sm:items-center sm:justify-between">
            <p>© {{ now()->year }} indirimGo. Tüm hakları saklıdır.</p>
            <div class="flex flex-wrap items-center gap-4">
                <a href="#" class="text-zinc-600 transition hover:text-zinc-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent) dark:text-zinc-300 dark:hover:text-white">Gizlilik</a>
                <a href="#" class="text-zinc-600 transition hover:text-zinc-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent) dark:text-zinc-300 dark:hover:text-white">Kullanım şartları</a>
                <a href="#" class="text-zinc-600 transition hover:text-zinc-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent) dark:text-zinc-300 dark:hover:text-white">Çerezler</a>
            </div>
        </div>
    </div>
</footer>
