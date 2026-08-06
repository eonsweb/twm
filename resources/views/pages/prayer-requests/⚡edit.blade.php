<?php

use App\Actions\PrayerRequests\SavePrayerRequest;
use App\Livewire\Forms\PrayerRequestForm;
use App\Models\PrayerRequest;
use App\PrayerRequestCategory;
use App\PrayerRequestPrivacy;
use App\PrayerRequestSource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Prayer Request')] class extends Component
{
    public PrayerRequest $prayerRequest;
    public PrayerRequestForm $form;
    public function mount(PrayerRequest $prayerRequest): void { Gate::authorize('update', $prayerRequest); abort_if($prayerRequest->trashed(), 404); $this->prayerRequest = $prayerRequest; $this->form->setPrayerRequest($prayerRequest); }
    public function save(SavePrayerRequest $save): void
    {
        $this->form->normalize(); $this->form->validate($this->form->rules()); $this->form->validateBusinessRules();
        $this->prayerRequest = $save->handle(Auth::user(), Arr::except($this->form->data(), ['status', 'priority', 'assigned_to']), $this->prayerRequest);
        session()->flash('success', __('Prayer request updated.')); $this->redirectRoute('prayer-requests.show', $this->prayerRequest, navigate: true);
    }
};
?>

<div class="mx-auto w-full max-w-5xl space-y-6"><flux:breadcrumbs><flux:breadcrumbs.item :href="route('prayer-requests.index')" wire:navigate>{{ __('Prayer requests') }}</flux:breadcrumbs.item><flux:breadcrumbs.item :href="route('prayer-requests.show', $prayerRequest)" wire:navigate>{{ $prayerRequest->reference_number }}</flux:breadcrumbs.item><flux:breadcrumbs.item>{{ __('Edit') }}</flux:breadcrumbs.item></flux:breadcrumbs><x-admin.page-header :title="__('Edit :reference', ['reference' => $prayerRequest->reference_number])" :description="__('Update requester details, consent, privacy, and the original confidential record.')" :eyebrow="__('Prayer request')"/>
<form wire:submit="save" class="space-y-6"><section class="space-y-5 rounded-xl border bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900"><div class="grid gap-4 sm:grid-cols-2"><flux:input wire:model="form.name" :label="__('Name')"/><flux:input wire:model="form.email" type="email" :label="__('Email')"/><flux:input wire:model="form.phone" :label="__('Phone')"/><flux:select wire:model="form.category" :label="__('Category')"><flux:select.option value="">{{ __('General') }}</flux:select.option>@foreach(PrayerRequestCategory::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select></div><flux:input wire:model="form.subject" :label="__('Subject')"/><flux:textarea wire:model="form.request" :label="__('Prayer request')" rows="9"/><div class="grid gap-4 sm:grid-cols-2"><flux:select wire:model="form.privacyLevel" :label="__('Privacy')">@foreach(PrayerRequestPrivacy::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select><flux:select wire:model="form.source" :label="__('Source')">@foreach(PrayerRequestSource::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select></div><div class="flex flex-wrap gap-5"><flux:checkbox wire:model="form.isAnonymous" :label="__('Anonymous')"/><flux:checkbox wire:model="form.allowContact" :label="__('Contact permitted')"/><flux:checkbox wire:model="form.allowPublication" :label="__('Publication consent recorded')"/></div><flux:textarea wire:model="form.adminNotes" :label="__('Private administrative notes')" rows="4"/></section><div class="flex justify-end gap-3"><flux:button :href="route('prayer-requests.show', $prayerRequest)" wire:navigate>{{ __('Cancel') }}</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">{{ __('Save changes') }}</flux:button></div></form></div>
