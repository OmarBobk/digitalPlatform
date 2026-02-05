<div
    x-data="{}"
    @open-create-modal.window="$wire.openCreate()"
    @open-edit-modal.window="$wire.startEdit($event.detail.userId)"
    @open-delete-modal.window="$wire.confirmDelete($event.detail.userId)"
    @open-block-modal.window="$wire.confirmBlock($event.detail.userId)"
    @open-unblock-modal.window="$wire.confirmUnblock($event.detail.userId)"
    @open-reset-password-modal.window="$wire.confirmResetPassword($event.detail.userId)"
    @open-verify-email-modal.window="$wire.confirmVerifyEmail($event.detail.userId)"
>
    {{-- Create user modal --}}
    <flux:modal wire:model.self="showCreate" class="max-w-2xl" variant="floating">
        <form wire:submit.prevent="saveCreate" class="space-y-6">
            <div class="flex flex-col gap-1">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                    {{ __('messages.create_user') }}
                </flux:heading>
                <flux:text class="text-zinc-600 dark:text-zinc-400">
                    {{ __('messages.create_user_hint') }}
                </flux:text>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('messages.name') }}</flux:label>
                    <flux:input wire:model.defer="newName" class="w-full" />
                    <flux:error name="newName" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('messages.username') }}</flux:label>
                    <flux:input wire:model.defer="newUsername" class="w-full" />
                    <flux:error name="newUsername" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('messages.email') }}</flux:label>
                    <flux:input type="email" wire:model.defer="newEmail" class="w-full" />
                    <flux:error name="newEmail" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('messages.password') }}</flux:label>
                    <flux:input type="password" wire:model.defer="newPassword" class="w-full" />
                    <flux:error name="newPassword" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('messages.confirm_password') }}</flux:label>
                    <flux:input type="password" wire:model.defer="newPasswordConfirmation" class="w-full" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('messages.phone') }}</flux:label>
                    <flux:input wire:model.defer="newPhone" class="w-full" />
                    <flux:error name="newPhone" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('messages.country_code') }}</flux:label>
                    <flux:select wire:model.defer="newCountryCode" placeholder="{{ __('messages.all') }}">
                        <flux:select.option value="">{{ __('messages.all') }}</flux:select.option>
                        <flux:select.option value="+963">+963</flux:select.option>
                        <flux:select.option value="+90">+90</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            <div class="space-y-3">
                <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">{{ __('messages.roles') }}</flux:heading>
                <div class="flex flex-wrap gap-3">
                    @foreach ($this->allRoles as $roleName)
                        <flux:checkbox
                            wire:model.defer="newRoles"
                            :value="$roleName"
                            :label="$roleName"
                        />
                    @endforeach
                </div>
            </div>

            <div class="space-y-3">
                <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">{{ __('messages.direct_permissions') }}</flux:heading>
                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('messages.direct_permissions_hint') }}</flux:text>
                <div class="flex flex-wrap gap-3">
                    @foreach ($this->allPermissions as $permName)
                        <flux:checkbox
                            wire:model.defer="newPermissions"
                            :value="$permName"
                            :label="$permName"
                        />
                    @endforeach
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:spacer />
                <flux:button type="button" variant="ghost" wire:click="closeCreate">
                    {{ __('messages.cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveCreate">
                    {{ __('messages.create_user') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit user modal --}}
    <flux:modal wire:model.self="showEdit" class="max-w-2xl" variant="floating">
        <form wire:submit.prevent="saveEdit" class="space-y-6">
            <div class="flex flex-col gap-1">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                    {{ __('messages.edit_user') }}
                </flux:heading>
                <flux:text class="text-zinc-600 dark:text-zinc-400">
                    {{ __('messages.edit_user_hint') }}
                </flux:text>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('messages.name') }}</flux:label>
                    <flux:input wire:model.defer="editName" class="w-full" />
                    <flux:error name="editName" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('messages.username') }}</flux:label>
                    <flux:input wire:model.defer="editUsername" class="w-full" />
                    <flux:error name="editUsername" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('messages.email') }}</flux:label>
                    <flux:input type="email" wire:model.defer="editEmail" class="w-full" />
                    <flux:error name="editEmail" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('messages.phone') }}</flux:label>
                    <flux:input wire:model.defer="editPhone" class="w-full" />
                    <flux:error name="editPhone" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('messages.country_code') }}</flux:label>
                    <flux:select wire:model.defer="editCountryCode" placeholder="{{ __('messages.all') }}">
                        <flux:select.option value="">{{ __('messages.all') }}</flux:select.option>
                        <flux:select.option value="+963">+963</flux:select.option>
                        <flux:select.option value="+90">+90</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            <div class="space-y-3">
                <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">{{ __('messages.roles') }}</flux:heading>
                <div class="flex flex-wrap gap-3">
                    @foreach ($this->allRoles as $roleName)
                        <flux:checkbox
                            wire:model.defer="editRoles"
                            :value="$roleName"
                            :label="$roleName"
                        />
                    @endforeach
                </div>
            </div>

            <div class="space-y-3">
                <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-100">{{ __('messages.direct_permissions') }}</flux:heading>
                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('messages.direct_permissions_hint') }}</flux:text>
                <div class="flex flex-wrap gap-3">
                    @foreach ($this->allPermissions as $permName)
                        <flux:checkbox
                            wire:model.defer="editPermissions"
                            :value="$permName"
                            :label="$permName"
                        />
                    @endforeach
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:spacer />
                <flux:button type="button" variant="ghost" wire:click="closeEdit">
                    {{ __('messages.cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveEdit">
                    {{ __('messages.update_user') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete confirmation --}}
    <flux:modal wire:model.self="showDeleteModal" class="max-w-md" variant="floating" @close="cancelDelete" @cancel="cancelDelete">
        <div class="space-y-6">
            <div class="flex items-start gap-4">
                <div class="flex size-11 items-center justify-center rounded-full bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400">
                    <flux:icon icon="trash" class="size-5" />
                </div>
                <div class="space-y-2">
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                        {{ __('messages.delete_user_title') }}
                    </flux:heading>
                    <flux:text class="text-zinc-600 dark:text-zinc-400">
                        {{ __('messages.delete_user_body', ['name' => $deleteUserName]) }}
                    </flux:text>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <flux:spacer />
                <flux:button variant="ghost" wire:click="cancelDelete">
                    {{ __('messages.cancel') }}
                </flux:button>
                <flux:button variant="danger" wire:click="deleteUser" wire:loading.attr="disabled" wire:target="deleteUser">
                    {{ __('messages.delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Block confirmation --}}
    <flux:modal wire:model.self="showBlockModal" class="max-w-md" variant="floating" @close="cancelBlock" @cancel="cancelBlock">
        <div class="space-y-6">
            <div class="flex items-start gap-4">
                <div class="flex size-11 items-center justify-center rounded-full bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                    <flux:icon icon="lock-closed" class="size-5" />
                </div>
                <div class="space-y-2">
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                        {{ __('messages.block_user_title') }}
                    </flux:heading>
                    <flux:text class="text-zinc-600 dark:text-zinc-400">
                        {{ __('messages.block_user_body', ['name' => $blockUserName]) }}
                    </flux:text>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <flux:spacer />
                <flux:button variant="ghost" wire:click="cancelBlock">{{ __('messages.cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="blockUser" wire:loading.attr="disabled" wire:target="blockUser">
                    {{ __('messages.block') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Unblock confirmation --}}
    <flux:modal wire:model.self="showUnblockModal" class="max-w-md" variant="floating" @close="cancelUnblock" @cancel="cancelUnblock">
        <div class="space-y-6">
            <div class="flex items-start gap-4">
                <div class="flex size-11 items-center justify-center rounded-full bg-green-50 text-green-600 dark:bg-green-500/10 dark:text-green-400">
                    <flux:icon icon="lock-open" class="size-5" />
                </div>
                <div class="space-y-2">
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                        {{ __('messages.unblock_user_title') }}
                    </flux:heading>
                    <flux:text class="text-zinc-600 dark:text-zinc-400">
                        {{ __('messages.unblock_user_body', ['name' => $unblockUserName]) }}
                    </flux:text>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <flux:spacer />
                <flux:button variant="ghost" wire:click="cancelUnblock">{{ __('messages.cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="unblockUser" wire:loading.attr="disabled" wire:target="unblockUser">
                    {{ __('messages.unblock') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Reset password modal --}}
    <flux:modal wire:model.self="showResetPasswordModal" class="max-w-md" variant="floating" @close="cancelResetPassword" @cancel="cancelResetPassword">
        <form wire:submit.prevent="resetPassword" class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                    {{ __('messages.reset_password') }}
                </flux:heading>
                <flux:text class="text-zinc-600 dark:text-zinc-400">
                    {{ __('messages.reset_password_hint') }}
                </flux:text>
            </div>
            <flux:field>
                <flux:label>{{ __('messages.new_password') }}</flux:label>
                <flux:input type="password" wire:model.defer="resetPasswordNew" class="w-full" />
                <flux:error name="resetPasswordNew" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('messages.confirm_password') }}</flux:label>
                <flux:input type="password" wire:model.defer="resetPasswordNewConfirmation" class="w-full" />
            </flux:field>
            <div class="flex flex-wrap items-center gap-2">
                <flux:spacer />
                <flux:button type="button" variant="ghost" wire:click="cancelResetPassword">{{ __('messages.cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="resetPassword">
                    {{ __('messages.reset_password') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Verify email confirmation --}}
    <flux:modal wire:model.self="showVerifyModal" class="max-w-md" variant="floating" @close="cancelVerify" @cancel="cancelVerify">
        <div class="space-y-6">
            <div class="flex items-start gap-4">
                <div class="flex size-11 items-center justify-center rounded-full bg-green-50 text-green-600 dark:bg-green-500/10 dark:text-green-400">
                    <flux:icon icon="envelope" class="size-5" />
                </div>
                <div class="space-y-2">
                    <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                        {{ __('messages.verify_email') }}
                    </flux:heading>
                    <flux:text class="text-zinc-600 dark:text-zinc-400">
                        {{ __('messages.verify_email_body', ['name' => $verifyUserName]) }}
                    </flux:text>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <flux:spacer />
                <flux:button variant="ghost" wire:click="cancelVerify">{{ __('messages.cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="verifyEmail" wire:loading.attr="disabled" wire:target="verifyEmail">
                    {{ __('messages.verify_email') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
