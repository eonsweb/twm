<?php

use App\Actions\Users\UpdateUser;
use App\Livewire\Forms\UserForm;
use App\Models\Person;
use App\Models\User;
use App\RoleName;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\Permission\Models\Role;

new #[Title('Edit administrator')] class extends Component
{
    use WithFileUploads;

    public User $user;

    public UserForm $form;

    public function mount(User $user): void
    {
        Gate::authorize('update', $user);

        $this->user = $user;
        $this->form->setUser($user);
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
            ->where(function ($query): void {
                $query->whereNull('user_id')->orWhere('user_id', $this->user->id);
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(100)
            ->get(['id', 'user_id', 'title', 'first_name', 'middle_name', 'last_name']);
    }

    public function save(UpdateUser $updateUser): void
    {
        Gate::authorize('update', $this->user);

        $this->form->normalize();
        $this->form->validate();

        $roles = Role::query()->whereKey($this->form->roleIds)->get(['id', 'name']);
        Gate::authorize('assignRoles', [$this->user, $roles->pluck('name')->all()]);

        $this->user = $updateUser->handle(
            auth()->user(),
            $this->user,
            $this->form->userData(),
            $roles->pluck('id')->all(),
            $this->form->personId,
            $this->form->photo,
            $this->form->removePhoto,
        );
        $this->form->setUser($this->user);
        $this->form->photo = null;
        $this->form->removePhoto = false;

        Flux::toast(variant: 'success', text: __('Administrator updated.'));
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('users.index')" wire:navigate>{{ __('Users') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('users.show', $user)" wire:navigate>{{ $user->name }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Edit') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-admin.page-header
        :title="__('Edit :name', ['name' => $user->name])"
        :description="__('Update account identity, administrative roles, photo, and optional person link.')"
        :eyebrow="__('Administration')"
    />

    <form wire:submit="save" class="space-y-6">
        <x-admin.user-form
            :form="$form"
            :roles="$this->roles"
            :people="$this->people"
            :current-photo-url="$user->photoUrl()"
        />

        <div class="sticky bottom-4 z-20 flex flex-wrap justify-end gap-3 rounded-xl border border-slate-200/80 bg-white/95 p-4 shadow-lg backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95">
            <flux:button :href="route('users.show', $user)" variant="ghost" wire:navigate>{{ __('Back') }}</flux:button>
            <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">
                {{ __('Save changes') }}
            </flux:button>
        </div>
    </form>
</div>
