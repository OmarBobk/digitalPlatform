<?php

use App\Actions\Categories\DeleteCategoryTree;
use App\Actions\Categories\GetCategories;
use App\Actions\Categories\GetParentCategories;
use App\Actions\Categories\ToggleCategoryStatus;
use App\Actions\Categories\UpsertCategory;
use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public string $sortBy = 'order';
    public string $sortDirection = 'asc';
    public int $perPage = 10;

    /**
     * Create-form state is intentionally isolated so we can fully reset it
     * when the panel closes; keeping defaults in one place avoids stale values.
     *
     * @var string
     */
    public string $newName = '';
    public ?int $newParentId = null;
    public ?int $newOrder = null;
    public ?string $newIcon = null;
    public $newImageFile = null;
    public bool $newIsActive = true;
    public ?int $editingCategoryId = null;

    /**
     * Delete modal state is stored on the component to avoid inline JS confirms.
     * If the modal is refactored away, make sure the delete action still passes
     * the correct category id and name.
     */
    public bool $showDeleteModal = false;
    public ?int $deleteCategoryId = null;
    public string $deleteCategoryName = '';

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'sortBy', 'sortDirection', 'perPage']);
        $this->resetPage();
    }

    /**
     * Validation rules live here so errors are user-facing instead of DB errors.
     * The order uniqueness must stay in sync with the DB constraint.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'newName' => ['required', 'string', 'max:255'],
            'newParentId' => ['nullable', 'integer', 'exists:categories,id'],
            'newOrder' => [
                'required',
                'integer',
                'min:0',
                Rule::unique('categories', 'order')->ignore($this->editingCategoryId),
            ],
            'newIcon' => ['nullable', 'string', 'max:255'],
            'newImageFile' => ['nullable', 'image', 'max:2048'],
            'newIsActive' => ['boolean'],
        ];
    }

    /**
     * Persist the category and place uploaded images in public/ for fast serving.
     * Changing storage location requires updating how images are rendered.
     */
    public function save(): void
    {
        $validated = $this->validate();

        app(UpsertCategory::class)->handle(
            $this->editingCategoryId,
            [
                'parent_id' => $validated['newParentId'],
                'name' => $validated['newName'],
                'order' => $validated['newOrder'],
                'icon' => $validated['newIcon'],
                'is_active' => $validated['newIsActive'],
            ],
            $this->newImageFile
        );

        $this->resetCreateForm();
        $this->resetPage();
        $this->dispatch('category-created');
    }

    public function startEdit(int $categoryId): void
    {
        $category = Category::query()->findOrFail($categoryId);

        $this->editingCategoryId = $category->id;
        $this->newName = $category->name;
        $this->newParentId = $category->parent_id;
        $this->newOrder = $category->order;
        $this->newIcon = $category->icon;
        $this->newImageFile = null;
        $this->newIsActive = $category->is_active;

        $this->dispatch('open-create-panel');
    }

    public function toggleStatus(int $categoryId): void
    {
        app(ToggleCategoryStatus::class)->handle($categoryId);
    }

    /**
     * Store delete context so the modal can render a clear confirmation message.
     */
    public function confirmDelete(int $categoryId): void
    {
        $category = Category::query()->findOrFail($categoryId);

        $this->deleteCategoryId = $category->id;
        $this->deleteCategoryName = $category->name;
        $this->showDeleteModal = true;
    }

    public function cancelDelete(): void
    {
        $this->resetDeleteModal();
    }

    /**
     * Deletes the category and its descendants in one operation to avoid orphans.
     */
    public function deleteCategory(?int $categoryId = null): void
    {
        $categoryId = $categoryId ?? $this->deleteCategoryId;

        if ($categoryId === null) {
            return;
        }

        app(DeleteCategoryTree::class)->handle($categoryId);

        $this->resetDeleteModal();
        $this->resetPage();
    }

    public function resetDeleteModal(): void
    {
        $this->reset(['showDeleteModal', 'deleteCategoryId', 'deleteCategoryName']);
    }

    public function resetCreateForm(): void
    {
        $this->reset(['newName', 'newParentId', 'newOrder', 'newIcon', 'newImageFile', 'newIsActive', 'editingCategoryId']);
        $this->resetValidation();
    }

    /**
     * Eager-load parents to avoid N+1 queries in the table view.
     */
    public function getCategoriesProperty(): LengthAwarePaginator
    {
        return app(GetCategories::class)->handle(
            $this->search,
            $this->statusFilter,
            $this->sortBy,
            $this->sortDirection,
            $this->perPage
        );
    }

    public function getParentCategoriesProperty(): Collection
    {
        return app(GetParentCategories::class)->handle();
    }

    /**
     * Uses global min/max so the placeholder reflects actual order bounds.
     */
    public function getOrderRangePlaceholderProperty(): string
    {
        $min = Category::min('order');
        $max = Category::max('order');

        if ($min === null || $max === null) {
            return __('messages.no_orders_yet');
        }

        return $min.' - '.$max;
    }

    public function render(): View
    {
        return $this->view()->title(__('messages.categories'));
    }
};
?>

<div
    class="flex h-full w-full flex-1 flex-col gap-6"
    x-data="{
        showFilters: true,
        showCreate: false,
        toggleCreate() {
            if (this.showCreate) {
                this.closeCreate();
                return;
            }

            this.showCreate = true;
        },
        closeCreate() {
            this.showCreate = false;
            $wire.resetCreateForm();
        },
    }"
    x-on:category-created.window="closeCreate()"
    x-on:open-create-panel.window="showCreate = true"
    data-test="categories-page"
>
    <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                <div class="flex size-12 items-center justify-center rounded-xl bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                    <flux:icon icon="tag" class="size-5" />
                </div>
                <div class="flex flex-col gap-2">
                    <div class="flex flex-col gap-1">
                        <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                            {{ __('messages.categories') }}
                        </flux:heading>
                        <flux:text class="text-zinc-600 dark:text-zinc-400">
                            {{ __('messages.categories_intro') }}
                        </flux:text>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400" role="status" aria-live="polite">
                        <span>{{ __('messages.showing') }}</span>
                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">
                            {{ $this->categories->count() }}
                        </span>
                        <span>{{ __('messages.of') }}</span>
                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">
                            {{ $this->categories->total() }}
                        </span>
                        <span>{{ __('messages.categories') }}</span>
                        @if ($statusFilter !== 'all')
                            <flux:badge class="capitalize">
                                {{ $statusFilter === 'active' ? __('messages.active') : __('messages.inactive_status') }}
                            </flux:badge>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <flux:button
                    type="button"
                    variant="primary"
                    icon="plus"
                    class="!bg-accent !text-accent-foreground hover:!bg-accent-hover"
                    x-on:click="toggleCreate()"
                >
                    {{ __('messages.new_category') }}
                </flux:button>
                <flux:button
                    type="button"
                    variant="ghost"
                    icon="adjustments-horizontal"
                    x-on:click="showFilters = !showFilters"
                >
                    {{ __('messages.filters') }}
                </flux:button>
                <flux:button
                    type="button"
                    variant="ghost"
                    icon="arrow-path"
                    wire:click="$refresh"
                    wire:loading.attr="disabled"
                >
                    {{ __('messages.refresh') }}
                </flux:button>
            </div>
        </div>

        {{-- Filters submit once to avoid chatty updates on each keystroke. --}}
        <form
            class="grid gap-4 pt-4"
            wire:submit.prevent="applyFilters"
            x-show="showFilters"
            x-cloak
            data-test="categories-filters"
        >
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <div class="grid gap-2">
                    <flux:input
                        name="search"
                        :label="__('messages.search')"
                        :placeholder="__('messages.search_placeholder')"
                        wire:model.defer="search"
                        class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                    />
                </div>
                <div class="grid gap-2">
                    <flux:select class="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                                 name="sortBy" label="{{ __('messages.sort_by') }}" wire:model.defer="sortBy">
                        <flux:select.option value="order">{{ __('messages.order') }}</flux:select.option>
                        <flux:select.option value="name">{{ __('messages.name') }}</flux:select.option>
                        <flux:select.option value="created_at">{{ __('messages.created') }}</flux:select.option>
                    </flux:select>
                </div>
                <div class="grid gap-2">
                    <flux:select class="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                                 name="sortDirection" label="{{ __('messages.direction') }}" wire:model.defer="sortDirection">
                        <flux:select.option value="asc">{{ __('messages.ascending') }}</flux:select.option>
                        <flux:select.option value="desc">{{ __('messages.descending') }}</flux:select.option>
                    </flux:select>
                </div>
                <div class="grid gap-2">
                    <flux:select class="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                                 name="perPage" label="{{ __('messages.per_page') }}" wire:model.defer="perPage">
                        <flux:select.option value="10">10</flux:select.option>
                        <flux:select.option value="25">25</flux:select.option>
                        <flux:select.option value="50">50</flux:select.option>
                    </flux:select>
                </div>
            </div>
            <div class="flex flex-wrap gap-3">
                <flux:select
                    class="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                    name="statusFilter"
                    label="{{ __('messages.status') }}"
                    wire:model.defer="statusFilter"
                >
                    <flux:select.option value="all">{{ __('messages.all') }}</flux:select.option>
                    <flux:select.option value="active">{{ __('messages.active') }}</flux:select.option>
                    <flux:select.option value="inactive">{{ __('messages.inactive_status') }}</flux:select.option>
                </flux:select>
                <div class="flex flex-wrap h-full items-end gap-2">
                    <flux:button type="submit" variant="primary" icon="magnifying-glass">
                        {{ __('messages.apply') }}
                    </flux:button>
                    <flux:button type="button" variant="ghost" icon="arrow-path" wire:click="resetFilters">
                        {{ __('messages.reset') }}
                    </flux:button>
                </div>
            </div>
        </form>
    </section>

    {{-- Shared create/edit panel keeps UX consistent; edit state is handled in Livewire. --}}
    <section
        class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900"
        x-show="showCreate"
        x-cloak
    >
        <form class="grid gap-5" wire:submit.prevent="save">
            <div class="flex flex-col gap-1">
                <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">
                    {{ $editingCategoryId ? __('messages.edit_category') : __('messages.create_category') }}
                </flux:heading>
                <flux:text class="text-zinc-600 dark:text-zinc-400">
                    {{ $editingCategoryId ? __('messages.edit_category_hint') : __('messages.category_slug_auto') }}
                </flux:text>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <flux:input
                        class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                        name="newName"
                        label="{{ __('messages.name') }}"
                        placeholder="{{ __('messages.category_name_placeholder') }}"
                        wire:model.defer="newName"
                    />
                    @error('newName')
                        <flux:text color="red">{{ $message }}</flux:text>
                    @enderror
                </div>
                <div class="grid gap-2">
                    <flux:select
                        class="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                        name="newParentId" label="{{ __('messages.parent_category') }}" wire:model.defer="newParentId" placeholder="{{ __('messages.no_parent') }}">
                        <flux:select.option value="">{{ __('messages.no_parent') }}</flux:select.option>
                        @foreach ($this->parentCategories as $parentCategory)
                            <flux:select.option value="{{ $parentCategory->id }}">{{ $parentCategory->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('newParentId')
                        <flux:text color="red">{{ $message }}</flux:text>
                    @enderror
                </div>
                <div class="grid gap-2">
                    <flux:input
                        class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                        name="newOrder"
                        label="{{ __('messages.order') }}"
                        type="number"
                        min="0"
                        step="1"
                        placeholder="{{ $this->orderRangePlaceholder }}"
                        wire:model.defer="newOrder"
                    />
                    @error('newOrder')
                        <flux:text color="red">{{ $message }}</flux:text>
                    @enderror
                </div>
                <div class="grid gap-2">
                    <flux:input
                        class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                        name="newIcon"
                        label="{{ __('messages.icon') }}"
                        placeholder="{{ __('messages.icon_placeholder') }}"
                        wire:model.defer="newIcon"
                    />
                    @error('newIcon')
                        <flux:text color="red">{{ $message }}</flux:text>
                    @enderror
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <flux:input
                        class:input="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                        name="newImageFile"
                        label="{{ __('messages.image') }}"
                        type="file"
                        accept="image/*"
                        wire:model="newImageFile"
                    />
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('messages.category_image_hint') }}
                    </flux:text>
                    <flux:text
                        class="text-xs text-zinc-500 dark:text-zinc-400"
                        wire:loading
                        wire:target="newImageFile"
                    >
                        {{ __('messages.uploading_image') }}
                    </flux:text>
                    @error('newImageFile')
                        <flux:text color="red">{{ $message }}</flux:text>
                    @enderror
                    {{-- Preview uses Livewire temp URLs; changing storage flow breaks this preview. --}}
                    @if ($newImageFile)
                        <div class="mt-2 flex items-center gap-3 rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800">
                            <div class="size-14 overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                                <img
                                    src="{{ $newImageFile->temporaryUrl() }}"
                                    alt="{{ __('messages.category_preview_alt') }}"
                                    class="size-full object-cover"
                                />
                            </div>
                            <div class="text-xs text-zinc-600 dark:text-zinc-300">
                                {{ __('messages.preview') }}
                            </div>
                        </div>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    <flux:label>{{ __('messages.active') }}:</flux:label>

                    <flux:switch class="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0" wire:model.defer="newIsActive">
                    </flux:switch>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:button
                    class="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                    type="submit" variant="primary" icon="plus" wire:loading.attr="disabled" wire:target="save">
                    {{ $editingCategoryId ? __('messages.update_category') : __('messages.create_category') }}
                </flux:button>
                <flux:button
                    class="focus:!border-(--color-accent) focus:!border-1 focus:!ring-0 focus:!outline-none focus:!ring-offset-0"
                    type="button" variant="ghost" x-on:click="closeCreate()">
                    {{ __('messages.cancel') }}
                </flux:button>
            </div>
        </form>
    </section>

    <section class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
            <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">
                {{ __('messages.categories') }}
            </flux:heading>
            <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                <span>{{ __('messages.total') }}</span>
                <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $this->categories->total() }}</span>
            </div>
        </div>

        <div class="overflow-x-auto" wire:loading.class="opacity-60">
            <div
                class="p-6"
                wire:loading.delay
                wire:target="applyFilters,resetFilters,$refresh,nextPage,previousPage,gotoPage"
            >
                <div class="grid gap-3">
                    <flux:skeleton class="h-4 w-36" />
                    <div class="grid gap-2">
                        <flux:skeleton class="h-10 w-full" />
                        <flux:skeleton class="h-10 w-full" />
                        <flux:skeleton class="h-10 w-full" />
                        <flux:skeleton class="h-10 w-full" />
                        <flux:skeleton class="h-10 w-full" />
                    </div>
                </div>
            </div>
            <div wire:loading.delay.remove>
                @if ($this->categories->count() === 0)
                <div class="flex flex-col items-center gap-3 p-10 text-center">
                    <div class="flex size-12 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-300">
                        <flux:icon icon="tag" class="size-5" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">
                            {{ __('messages.no_categories_yet') }}
                        </flux:heading>
                        <flux:text class="text-zinc-600 dark:text-zinc-400">
                            {{ __('messages.create_first_category') }}
                        </flux:text>
                    </div>
                    <flux:button
                        type="button"
                        variant="primary"
                        icon="plus"
                        class="!bg-accent !text-accent-foreground hover:!bg-accent-hover"
                        x-on:click="showCreate = true"
                    >
                        {{ __('messages.add_category') }}
                    </flux:button>
                </div>
                @else
                <table class="min-w-full divide-y divide-zinc-100 text-sm dark:divide-zinc-800" data-test="categories-table">
                    <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                        <tr>
                            <th class="px-5 py-3 text-start font-semibold">{{ __('messages.category') }}</th>
                            <th class="px-5 py-3 text-start font-semibold">{{ __('messages.parent') }}</th>
                            <th class="px-5 py-3 text-start font-semibold">{{ __('messages.order') }}</th>
                            <th class="px-5 py-3 text-start font-semibold">{{ __('messages.status') }}</th>
                            <th class="px-5 py-3 text-start font-semibold">{{ __('messages.created') }}</th>
                            <th class="px-5 py-3 text-end font-semibold">{{ __('messages.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->categories as $category)
                            <tr
                                class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/60"
                                wire:key="category-{{ $category->id }}"
                            >
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="size-10 overflow-hidden rounded-lg border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800">
                                            @if ($category->image)
                                                <img
                                                    src="{{ asset($category->image) }}"
                                                    alt="{{ $category->name }}"
                                                    class="size-full object-cover"
                                                    loading="lazy"
                                                />
                                            @else
                                                <div class="flex size-full items-center justify-center text-zinc-500 dark:text-zinc-300">
                                                    <flux:icon icon="photo" class="size-4" />
                                                </div>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="truncate font-semibold text-zinc-900 dark:text-zinc-100">
                                                    {{ $category->name }}
                                                </span>
                                                @if ($category->icon)
                                                    <span class="rounded-md bg-zinc-100 px-2 py-0.5 text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                                        {{ $category->icon }}
                                                    </span>
                                                @endif
                                            </div>
                                            {{-- Prefix with parent slug for clearer hierarchy; requires eager-loaded parent. --}}
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                @if ($category->parent)
                                                    /{{ $category->parent->slug }}/{{ $category->slug }}
                                                @else
                                                    /{{ $category->slug }}
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">
                                    {{ $category->parent_id ? '#'.$category->parent->name : __('messages.root') }}
                                </td>
                                <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">
                                    {{ $category->order }}
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center justify-start">
                                        {{-- Switch toggles server state directly; binding would require per-row Livewire state. --}}
                                        <flux:switch
                                            :checked="$category->is_active"
                                            wire:click="toggleStatus({{ $category->id }})"
                                            aria-label="{{ __('messages.toggle_status_for', ['name' => $category->name]) }}"
                                        />
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-zinc-600 dark:text-zinc-300">
                                    {{ $category->created_at?->format('M d, Y') ?? '—' }}
                                </td>
                                <td class="px-5 py-4 text-end">
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button variant="ghost" icon="ellipsis-vertical" />
                                        <flux:menu>
                                            <flux:menu.item icon="pencil" wire:click="startEdit({{ $category->id }})">
                                                {{ __('messages.edit') }}
                                            </flux:menu.item>
                                            <flux:menu.item icon="eye">{{ __('messages.view') }}</flux:menu.item>
                                            <flux:menu.separator />
                                            <flux:menu.item
                                                variant="danger"
                                                icon="trash"
                                                wire:click="confirmDelete({{ $category->id }})"
                                            >
                                                {{ __('messages.delete') }}
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>

        <div class="border-t border-zinc-100 px-5 py-4 dark:border-zinc-800">
            {{ $this->categories->links() }}
        </div>
    </section>

    {{-- Modal is Livewire-controlled to avoid brittle JS confirms and keep i18n strings. --}}
    <flux:modal
        wire:model.self="showDeleteModal"
        class="max-w-md"
        variant="floating"
        @close="cancelDelete"
        @cancel="cancelDelete"
    >
        <div class="space-y-6">
            <div class="flex items-start gap-4">
                <div class="flex size-11 items-center justify-center rounded-full bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400">
                    <flux:icon icon="trash" class="size-5" />
                </div>
                <div class="space-y-2">
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                        {{ __('messages.delete_category_title') }}
                    </flux:heading>
                    <flux:text class="text-zinc-600 dark:text-zinc-400">
                        {{ __('messages.delete_category_body', ['name' => $deleteCategoryName]) }}
                    </flux:text>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:spacer />
                <flux:button variant="ghost" wire:click="cancelDelete">
                    {{ __('messages.cancel') }}
                </flux:button>
                <flux:button
                    variant="danger"
                    wire:click="deleteCategory"
                    wire:loading.attr="disabled"
                    wire:target="deleteCategory"
                >
                    {{ __('messages.delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
