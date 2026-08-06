<?php

use App\Actions\PrayerRequests\ChangePrayerRequestWorkflow;
use App\Actions\PrayerRequests\SavePrayerRequest;
use App\Livewire\Forms\PrayerRequestForm;
use App\Models\PrayerRequest;
use App\Models\User;
use App\PrayerRequestCategory;
use App\PrayerRequestPriority;
use App\PrayerRequestPrivacy;
use App\PrayerRequestSource;
use App\PrayerRequestStatus;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Add Prayer Request')] class extends Component
{
    public PrayerRequestForm $form;
    public function mount(): void { Gate::authorize('create', PrayerRequest::class); }
    #[Computed] public function assignees() { return User::query()->where('account_status', 'active')->permission('prayer-requests.update')->orderBy('name')->get(['id', 'name']); }

    public function save(SavePrayerRequest $save, ChangePrayerRequestWorkflow $workflow): void
    {
        $this->form->normalize(); $this->form->validate($this->form->rules()); $this->form->validateBusinessRules();
        $status = PrayerRequestStatus::from($this->form->status); $priority = PrayerRequestPriority::from($this->form->priority); $assigneeId = $this->form->assignedTo;
        $data = Arr::except($this->form->data(), ['status', 'priority', 'assigned_to']);
        $request = $save->handle(Auth::user(), $data + ['status' => PrayerRequestStatus::New->value, 'priority' => PrayerRequestPriority::Normal->value, 'assigned_to' => null]);
        if ($priority !== PrayerRequestPriority::Normal) { $request = $workflow->changePriority(Auth::user(), $request, $priority); }
        if ($assigneeId !== null) { $request = $workflow->assign(Auth::user(), $request, User::findOrFail($assigneeId)); }
        if ($status !== PrayerRequestStatus::New && $status !== $request->status) { $request = $workflow->changeStatus(Auth::user(), $request, $status); }
        session()->flash('success', __('Prayer request created.')); $this->redirectRoute('prayer-requests.show', $request, navigate: true);
    }
};
?>

<div class="mx-auto w-full max-w-5xl space-y-6"><flux:breadcrumbs><flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item><flux:breadcrumbs.item :href="route('prayer-requests.index')" wire:navigate>{{ __('Prayer requests') }}</flux:breadcrumbs.item><flux:breadcrumbs.item>{{ __('Add') }}</flux:breadcrumbs.item></flux:breadcrumbs><x-admin.page-header :title="__('Add prayer request')" :description="__('Record a request received by staff, phone, service, or another trusted channel.')" :eyebrow="__('Care management')"/>
<form wire:submit="save" class="space-y-6"><section class="space-y-5 rounded-xl border bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900"><flux:heading size="lg">{{ __('Requester and request') }}</flux:heading><div class="grid gap-4 sm:grid-cols-2"><flux:input wire:model="form.name" :label="__('Name')"/><flux:input wire:model="form.email" type="email" :label="__('Email')"/><flux:input wire:model="form.phone" :label="__('Phone')"/><flux:select wire:model="form.category" :label="__('Category')"><flux:select.option value="">{{ __('General') }}</flux:select.option>@foreach(PrayerRequestCategory::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select></div><flux:input wire:model="form.subject" :label="__('Subject')"/><flux:textarea wire:model="form.request" :label="__('Prayer request')" rows="8"/><div class="flex flex-wrap gap-5"><flux:checkbox wire:model="form.isAnonymous" :label="__('Anonymous')"/><flux:checkbox wire:model="form.allowContact" :label="__('Contact permitted')"/><flux:checkbox wire:model="form.allowPublication" :label="__('Publication consent recorded')"/></div></section>
<section class="space-y-5 rounded-xl border bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900"><flux:heading size="lg">{{ __('Workflow') }}</flux:heading><div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"><flux:select wire:model="form.privacyLevel" :label="__('Privacy')">@foreach(PrayerRequestPrivacy::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select><flux:select wire:model="form.source" :label="__('Source')">@foreach(PrayerRequestSource::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select><flux:select wire:model="form.priority" :label="__('Priority')">@foreach(PrayerRequestPriority::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select><flux:select wire:model="form.status" :label="__('Status')">@foreach(PrayerRequestStatus::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select><flux:select wire:model="form.assignedTo" :label="__('Assignee')"><flux:select.option value="">{{ __('Unassigned') }}</flux:select.option>@foreach($this->assignees as $user)<flux:select.option :value="$user->id">{{ $user->name }}</flux:select.option>@endforeach</flux:select></div><flux:textarea wire:model="form.adminNotes" :label="__('Private administrative notes')" rows="4"/></section>
<div class="flex justify-end gap-3"><flux:button :href="route('prayer-requests.index')" wire:navigate>{{ __('Cancel') }}</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">{{ __('Create request') }}</flux:button></div></form></div>
