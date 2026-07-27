@props([
    'form',
    'permissionGroups',
    'canAssignPermissions' => false,
    'nameDisabled' => false,
])

<div class="space-y-6">
    <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
        <div class="mb-5">
            <flux:heading size="lg">{{ __('Role details') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Names are stored as lowercase kebab-case and displayed in a readable format.') }}</flux:text>
        </div>

        <flux:input
            wire:model="form.name"
            :label="__('Role name')"
            :description="__('For example: service-coordinator')"
            maxlength="100"
            required
            :disabled="$nameDisabled"
        />
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-col gap-4 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800">
            <div>
                <flux:heading size="lg">{{ __('Permission matrix') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ trans_choice(':count permission selected|:count permissions selected', count($form->permissionNames), ['count' => count($form->permissionNames)]) }}
                </flux:text>
            </div>

            @if ($canAssignPermissions)
                <div class="flex flex-wrap gap-2">
                    <flux:button type="button" variant="outline" size="sm" wire:click="selectAllPermissions">
                        {{ __('Select all') }}
                    </flux:button>
                    <flux:button type="button" variant="ghost" size="sm" wire:click="clearAllPermissions">
                        {{ __('Clear all') }}
                    </flux:button>
                </div>
            @endif
        </div>

        <div class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($permissionGroups as $module => $permissions)
                @php
                    $groupNames = $permissions->pluck('name')->all();
                    $selectedCount = count(array_intersect($form->permissionNames, $groupNames));
                @endphp

                <fieldset
                    class="rounded-xl border border-slate-200 p-4 dark:border-zinc-700"
                    wire:key="permission-group-{{ $module }}"
                >
                    <legend class="px-1 text-sm font-semibold text-slate-900 dark:text-white">
                        {{ \Illuminate\Support\Str::headline($module) }}
                    </legend>

                    <div class="mb-4 flex items-center justify-between gap-3">
                        <span class="text-xs text-slate-500 dark:text-zinc-400">
                            {{ __(':selected of :total selected', ['selected' => $selectedCount, 'total' => $permissions->count()]) }}
                        </span>
                        @if ($canAssignPermissions)
                            <flux:button
                                type="button"
                                variant="ghost"
                                size="sm"
                                wire:click="togglePermissionGroup('{{ $module }}')"
                            >
                                {{ $selectedCount === $permissions->count() ? __('Clear') : __('Select all') }}
                            </flux:button>
                        @endif
                    </div>

                    <flux:checkbox.group wire:model="form.permissionNames" class="grid gap-3">
                        @foreach ($permissions as $permission)
                            <flux:checkbox
                                :value="$permission->name"
                                :label="\Illuminate\Support\Str::headline(\Illuminate\Support\Str::after($permission->name, '.'))"
                                :description="$permission->name"
                                :disabled="! $canAssignPermissions"
                                wire:key="permission-{{ $permission->id }}"
                            />
                        @endforeach
                    </flux:checkbox.group>
                </fieldset>
            @endforeach
        </div>

        <div class="px-5 pb-5">
            <flux:error name="form.permissionNames" />
            <flux:error name="form.permissionNames.*" />
        </div>
    </section>
</div>
