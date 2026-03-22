<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">{{ __('messages.custom_prices') }}</flux:heading>
        <flux:button variant="primary" size="sm" wire:click="openCreate">{{ __('messages.add_custom_price') }}</flux:button>
    </div>
    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('messages.custom_prices_intro') }}</flux:text>

    @if ($rows->isEmpty())
        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('messages.no_custom_prices_yet') }}</flux:text>
    @else
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/80">
                    <tr>
                        <th class="px-4 py-3 text-start font-semibold text-zinc-900 dark:text-zinc-100">{{ __('messages.product') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-zinc-900 dark:text-zinc-100">{{ __('messages.custom_price') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-zinc-900 dark:text-zinc-100">{{ __('messages.note') }}</th>
                        <th class="px-4 py-3 text-start font-semibold text-zinc-900 dark:text-zinc-100">{{ __('messages.set_by') }}</th>
                        <th class="px-4 py-3 text-end font-semibold text-zinc-900 dark:text-zinc-100">{{ __('messages.options') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($rows as $row)
                        @php
                            $belowCost = $row->product
                                && $row->product->entry_price !== null
                                && (float) $row->price < (float) $row->product->entry_price;
                        @endphp
                        <tr wire:key="upp-{{ $row->id }}">
                            <td class="px-4 py-3 text-zinc-900 dark:text-zinc-100">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span>{{ $row->product?->name ?? '—' }}</span>
                                    @if ($belowCost)
                                        <flux:badge color="amber" size="sm" variant="subtle">{{ __('messages.below_cost') }}</flux:badge>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100" dir="ltr">
                                {{ config('billing.currency_symbol', '$') }}{{ number_format((float) $row->price, 2) }}
                            </td>
                            <td class="max-w-xs truncate px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $row->note ?? '—' }}</td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $row->creator?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-end">
                                <div class="inline-flex flex-wrap justify-end gap-1">
                                    <flux:button variant="ghost" size="sm" wire:click="openEdit({{ $row->id }})">{{ __('messages.edit') }}</flux:button>
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        class="text-red-600 hover:text-red-700 dark:text-red-400"
                                        wire:click="deleteRow({{ $row->id }})"
                                        wire:confirm="{{ __('messages.user_product_price_delete_confirm') }}"
                                    >
                                        {{ __('messages.delete') }}
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <flux:modal wire:model.self="showModal" class="max-w-lg">
        <div class="space-y-4">
            <flux:heading size="lg">{{ $editingRowId ? __('messages.edit_custom_price') : __('messages.add_custom_price') }}</flux:heading>

            @if ($editingRowId === null)
                <flux:field>
                    <flux:label>{{ __('messages.user_product_price_search_and_pick') }}</flux:label>
                    <flux:input
                        wire:model.live.debounce.300ms="productSearch"
                        :placeholder="__('messages.product_search_placeholder')"
                        autocomplete="off"
                    />
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('messages.user_product_price_search_hint') }}</flux:text>
                    <div
                        class="mt-2 max-h-52 overflow-y-auto rounded-lg border border-zinc-200 dark:border-zinc-600"
                        wire:loading.class.delay="opacity-60"
                        wire:target="productSearch"
                    >
                        @forelse ($this->filteredProducts as $p)
                            <button
                                type="button"
                                wire:key="user-product-pick-{{ $p->id }}"
                                wire:click="selectProduct({{ $p->id }})"
                                @class([
                                    'flex w-full items-center gap-2 border-b border-zinc-100 px-3 py-2.5 text-start text-sm transition last:border-b-0 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/80' => true,
                                    'bg-sky-50 font-medium text-sky-900 dark:bg-sky-950/50 dark:text-sky-100' => $selectedProductId === $p->id,
                                ])
                            >
                                <span class="min-w-0 flex-1 text-zinc-900 dark:text-zinc-100">{{ $p->name }}</span>
                                @if ($selectedProductId === $p->id)
                                    <flux:badge color="sky" size="sm" variant="subtle">{{ __('messages.user_product_price_selected') }}</flux:badge>
                                @endif
                            </button>
                        @empty
                            <div class="px-3 py-6 text-center">
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('messages.user_product_price_no_matches') }}</flux:text>
                            </div>
                        @endforelse
                    </div>
                    @if ($selectedProductId !== null)
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <flux:button variant="ghost" size="sm" wire:click="clearSelectedProduct">
                                {{ __('messages.user_product_price_clear_selection') }}
                            </flux:button>
                        </div>
                    @endif
                    @error('selectedProductId')
                        <flux:text color="red">{{ $message }}</flux:text>
                    @enderror
                </flux:field>
            @else
                <flux:field>
                    <flux:label>{{ __('messages.product') }}</flux:label>
                    <flux:text class="font-medium">{{ $editingProductName ?? '—' }}</flux:text>
                </flux:field>
            @endif

            @if ($this->productPricingPreview)
                @php
                    $sym = config('billing.currency_symbol', '$');
                    $fmt = fn (float $n) => $sym . number_format($n, 2);
                @endphp
                <div class="rounded-lg border border-zinc-200 bg-zinc-50/80 p-3 text-sm dark:border-zinc-600 dark:bg-zinc-800/50">
                    <flux:text class="mb-2 font-medium text-zinc-700 dark:text-zinc-200">{{ __('messages.user_product_price_catalog_context') }}</flux:text>
                    <dl class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <div>
                            <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('messages.entry_price') }}</dt>
                            <dd class="font-mono text-zinc-900 dark:text-zinc-100" dir="ltr">
                                {{ $this->productPricingPreview['entry_price'] !== null ? $fmt($this->productPricingPreview['entry_price']) : '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('messages.retail_price') }}</dt>
                            <dd class="font-mono text-zinc-900 dark:text-zinc-100" dir="ltr">{{ $fmt($this->productPricingPreview['retail_price']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('messages.wholesale_price') }}</dt>
                            <dd class="font-mono text-zinc-900 dark:text-zinc-100" dir="ltr">{{ $fmt($this->productPricingPreview['wholesale_price']) }}</dd>
                        </div>
                    </dl>
                </div>
            @endif

            <flux:field>
                <flux:label>{{ __('messages.custom_price') }}</flux:label>
                <flux:input type="number" step="0.01" min="0" wire:model="price" dir="ltr" />
                @error('price')
                    <flux:text color="red">{{ $message }}</flux:text>
                @enderror
            </flux:field>

            <flux:field>
                <flux:label>{{ __('messages.note') }}</flux:label>
                <flux:textarea wire:model="note" rows="2" />
                @error('note')
                    <flux:text color="red">{{ $message }}</flux:text>
                @enderror
            </flux:field>

            <div class="flex flex-wrap justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="closeModal">{{ __('messages.cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="save">{{ __('messages.save') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
