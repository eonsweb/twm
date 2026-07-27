<?php

use App\AccountStatus;
use App\Actions\Users\CreateAdminUser;
use App\Livewire\Forms\UserForm;
use App\Models\Person;
use App\Models\User;
use App\RoleName;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\Permission\Models\Role;

new #[Title('Create user')] class extends Component
{
    use WithFileUploads;

    public UserForm $form;

    public string $accountStatus = AccountStatus::Active->value;

    public string $suspensionReason = '';

    public function mount(): void
    {
        Gate::authorize('create', User::class);
    }

    #[Computed]
    public function roles()
    {
        $actorPermissionNames = auth()->user()->getAllPermissions()->pluck('name');

        return Role::query()
            ->when(
                ! auth()->user()->hasRole(RoleName::SuperAdmin),
                fn ($query) => $query
                    ->where('name', '!=', RoleName::SuperAdmin->value)
                    ->whereDoesntHave(
                        'permissions',
                        fn ($query) => $query->whereNotIn('name', $actorPermissionNames),
                    ),
            )
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function people()
    {
        return Person::query()
            ->whereNull('user_id')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(100)
            ->get(['id', 'title', 'first_name', 'middle_name', 'last_name']);
    }

    public function save(CreateAdminUser $createAdminUser): void
    {
        Gate::authorize('create', User::class);

        $this->form->normalize();
        $this->form->validate();
        $statusData = $this->validate([
            'accountStatus' => ['required', Rule::enum(AccountStatus::class)],
            'suspensionReason' => [
                Rule::requiredIf($this->accountStatus === AccountStatus::Suspended->value),
                'nullable',
                'string',
                'min:5',
                'max:2000',
            ],
        ]);

        $roles = Role::query()->whereKey($this->form->roleIds)->get(['id', 'name']);
        Gate::authorize('assignRoles', [new User, $roles->pluck('name')->all()]);

        $user = $createAdminUser->handle(
            auth()->user(),
            $this->form->userData(),
            $roles->pluck('id')->all(),
            AccountStatus::from($statusData['accountStatus']),
            $statusData['suspensionReason'] ?: null,
            $this->form->personId,
            $this->form->photo,
        );

        Flux::toast(
            variant: 'success',
            text: __(
                'Account created for :name (@:username). The email is already verified. Temporary password: password. The user must change it after signing in.',
                ['name' => $user->name, 'username' => $user->username],
            ),
        );
        $this->redirectRoute('users.show', $user, navigate: true);
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('users.index')" wire:navigate>{{ __('Users') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Create user') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-admin.page-header
        :title="__('Create user')"
        :description="__('Create a verified account with administrative access and a one-time temporary password.')"
        :eyebrow="__('Administration')"
    />

    <form wire:submit="save" class="space-y-6">
        <x-admin.user-form
            :form="$form"
            :roles="$this->roles"
            :people="$this->people"
            :account-statuses="\App\AccountStatus::cases()"
            :account-status="$accountStatus"
            :show-account-creation-options="true"
        />

        <div class="sticky bottom-4 z-20 flex flex-wrap justify-end gap-3 rounded-xl border border-slate-200/80 bg-white/95 p-4 shadow-lg backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95">
            <flux:button :href="route('users.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary" icon="user-plus" wire:loading.attr="disabled">
                {{ __('Create user') }}
            </flux:button>
        </div>
    </form>
</div>
