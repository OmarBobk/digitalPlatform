<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Themes\Tailwind;

final class UsersTable extends PowerGridComponent
{
    public string $tableName = 'users';

    public function customThemeClass(): ?string
    {
        return Tailwind::class;
    }

    protected $listeners = ['users-updated' => '$refresh'];

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function setUp(): array
    {
        return [
            PowerGrid::header()
                ->showSearchInput(),
            PowerGrid::footer()
                ->showPerPage(10, [10, 25, 50])
                ->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        return User::query()
            ->select([
                'id',
                'name',
                'username',
                'email',
                'phone',
                'country_code',
                'email_verified_at',
                'blocked_at',
                'last_login_at',
                'created_at',
            ])
            ->with('roles:id,name');
    }

    public function relationSearch(): array
    {
        return [
            'roles' => ['name'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('name')
            ->add('username')
            ->add('email')
            ->add('phone')
            ->add('country_code')
            ->add('roles_display', fn (User $user) => $user->roles->pluck('name')->implode(', ') ?: '—')
            ->add('phone_display', fn (User $user) => trim(($user->country_code ?? '').' '.($user->phone ?? '')) ?: '—')
            ->add('status_display', fn (User $user) => $user->blocked_at ? __('messages.blocked') : __('messages.active'))
            ->add('email_verified_at')
            ->add('email_verified_at_formatted', fn (User $user) => $user->email_verified_at?->format('M d, Y') ?? '—')
            ->add('last_login_at')
            ->add('last_login_at_formatted', fn (User $user) => $user->last_login_at?->format('M d, Y H:i') ?? '—')
            ->add('created_at')
            ->add('created_at_formatted', fn (User $user) => Carbon::parse($user->created_at)->format('M d, Y'));
    }

    public function columns(): array
    {
        return [
            Column::make(__('messages.id'), 'id')
                ->sortable()
                ->searchable(),

            Column::make(__('messages.name'), 'name')
                ->sortable()
                ->searchable(),

            Column::make(__('messages.email'), 'email')
                ->sortable()
                ->searchable(),

            Column::make(__('messages.phone'), 'phone_display'),

            Column::make(__('messages.username'), 'username')
                ->sortable()
                ->searchable(),

            Column::make(__('messages.roles'), 'roles_display'),

            Column::make(__('messages.status'), 'status_display', 'blocked_at')
                ->sortable(),

            Column::make(__('messages.email_verified'), 'email_verified_at_formatted', 'email_verified_at'),

            Column::make(__('messages.last_login'), 'last_login_at_formatted', 'last_login_at')
                ->sortable(),

            Column::action(__('messages.actions')),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('blocked_at', 'blocked_at')
                ->dataSource([
                    ['id' => 'all', 'name' => __('messages.all')],
                    ['id' => '0', 'name' => __('messages.active')],
                    ['id' => '1', 'name' => __('messages.blocked')],
                ])
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(function (Builder $query, string $value): void {
                    if ($value === '0') {
                        $query->whereNull('blocked_at');
                    } elseif ($value === '1') {
                        $query->whereNotNull('blocked_at');
                    }
                }),
        ];
    }

    public function actions(User $row): array
    {
        $actions = [];

        $viewUrl = route('admin.users.show', ['user' => $row->id]);
        $actions[] = \PowerComponents\LivewirePowerGrid\Button::add('view')
            ->slot(__('messages.view_customer'))
            ->icon('eye')
            ->class('pg-btn-white dark:ring-pg-primary-600 dark:border-pg-primary-600 dark:hover:bg-pg-primary-700 dark:ring-offset-pg-primary-800 dark:text-pg-primary-300 dark:bg-pg-primary-700')
            ->tag('a')
            ->attributes(['href' => $viewUrl, 'wire:navigate' => true]);

        $actions[] = \PowerComponents\LivewirePowerGrid\Button::add('edit')
            ->slot(__('messages.edit'))
            ->icon('pencil')
            ->class('pg-btn-white dark:ring-pg-primary-600 dark:border-pg-primary-600 dark:hover:bg-pg-primary-700 dark:ring-offset-pg-primary-800 dark:text-pg-primary-300 dark:bg-pg-primary-700')
            ->dispatch('open-edit-modal', ['userId' => $row->id]);

        if ($row->blocked_at) {
            $actions[] = \PowerComponents\LivewirePowerGrid\Button::add('unblock')
                ->slot(__('messages.unblock'))
                ->icon('lock-open')
                ->class('pg-btn-white dark:ring-pg-primary-600 dark:border-pg-primary-600 dark:hover:bg-pg-primary-700 dark:ring-offset-pg-primary-800 dark:text-pg-primary-300 dark:bg-pg-primary-700')
                ->dispatch('open-unblock-modal', ['userId' => $row->id]);
        } else {
            $actions[] = \PowerComponents\LivewirePowerGrid\Button::add('block')
                ->slot(__('messages.block'))
                ->icon('lock-closed')
                ->class('pg-btn-white dark:ring-pg-primary-600 dark:border-pg-primary-600 dark:hover:bg-pg-primary-700 dark:ring-offset-pg-primary-800 dark:text-pg-primary-300 dark:bg-pg-primary-700')
                ->dispatch('open-block-modal', ['userId' => $row->id]);
        }

        $actions[] = \PowerComponents\LivewirePowerGrid\Button::add('reset-password')
            ->slot(__('messages.reset_password'))
            ->icon('key')
            ->class('pg-btn-white dark:ring-pg-primary-600 dark:border-pg-primary-600 dark:hover:bg-pg-primary-700 dark:ring-offset-pg-primary-800 dark:text-pg-primary-300 dark:bg-pg-primary-700')
            ->dispatch('open-reset-password-modal', ['userId' => $row->id]);

        if (! $row->email_verified_at) {
            $actions[] = \PowerComponents\LivewirePowerGrid\Button::add('verify-email')
                ->slot(__('messages.verify_email'))
                ->icon('envelope')
                ->class('pg-btn-white dark:ring-pg-primary-600 dark:border-pg-primary-600 dark:hover:bg-pg-primary-700 dark:ring-offset-pg-primary-800 dark:text-pg-primary-300 dark:bg-pg-primary-700')
                ->dispatch('open-verify-email-modal', ['userId' => $row->id]);
        }

        $actions[] = \PowerComponents\LivewirePowerGrid\Button::add('delete')
            ->slot(__('messages.delete'))
            ->icon('trash')
            ->class('pg-btn-white dark:ring-red-600 dark:border-red-600 dark:hover:bg-red-700 dark:ring-offset-pg-primary-800 dark:text-red-300 dark:bg-red-700')
            ->dispatch('open-delete-modal', ['userId' => $row->id]);

        return $actions;
    }
}
