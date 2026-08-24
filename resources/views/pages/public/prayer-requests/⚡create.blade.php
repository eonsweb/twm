<?php

use App\Actions\PrayerRequests\SavePrayerRequest;
use App\Livewire\Forms\PrayerRequestForm;
use App\Models\User;
use App\PrayerRequestCategory;
use App\PrayerRequestPrivacy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.public'), Title('Submit a Prayer Request')] class extends Component
{
    public PrayerRequestForm $form;
    public string $website = '';
    public int $formStartedAt;
    public ?string $successReference = null;

    public function mount(): void { $this->formStartedAt = now()->timestamp; }

    public function submit(SavePrayerRequest $savePrayerRequest): void
    {
        if ($this->website !== '' || now()->timestamp - $this->formStartedAt < 3) {
            throw ValidationException::withMessages(['form.subject' => __('Please review the form and try again.')]);
        }

        $key = 'prayer-request:'.hash('sha256', (string) request()->ip().'|'.Str::limit((string) request()->userAgent(), 120));
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['form.subject' => __('Too many requests have been submitted. Please try again later.')]);
        }

        $this->form->normalize();
        $this->form->validate($this->form->rules(public: true));
        $this->form->validateBusinessRules(public: true);
        RateLimiter::increment($key, decaySeconds: 3600);
        $actor = Auth::user();
        $prayerRequest = $savePrayerRequest->handle(
            $actor instanceof User ? $actor : null,
            $this->form->data(),
            publicSubmission: true,
            metadata: ['ip_address' => request()->ip(), 'user_agent' => Str::limit((string) request()->userAgent(), 1000)],
        );

        $this->successReference = $prayerRequest->reference_number;
        $this->form->reset();
        $this->form->privacyLevel = PrayerRequestPrivacy::Private->value;
        $this->formStartedAt = now()->timestamp;
    }
};
?>

<div>
    <header class="bg-church-maroon-950 text-white"><div class="mx-auto max-w-4xl px-4 pb-16 pt-28 sm:px-6 sm:pb-20 sm:pt-32 lg:px-8 lg:pt-36"><p class="font-semibold text-church-gold-400">{{ __('Prayer and care') }}</p><h1 class="mt-2 text-4xl font-bold tracking-tight sm:text-5xl">{{ __('Submit a prayer request') }}</h1><p class="mt-4 max-w-3xl text-lg text-white/80">{{ __('Your request is confidential by default and will be handled with care by authorized prayer-team members.') }}</p></div></header>
    <section class="mx-auto max-w-4xl bg-white px-4 py-14 text-zinc-950 sm:px-6 lg:px-8">

    @if ($successReference)
        <flux:callout class="mt-8" variant="success" icon="check-circle" heading="{{ __('Your prayer request was received') }}">
            <p>{{ __('Reference: :reference', ['reference' => $successReference]) }}</p>
            <p class="mt-2">{{ __('Please keep this reference for your records. It cannot be used to access the request online, and no confidential details are shown here.') }}</p>
        </flux:callout>
    @else
        <form wire:submit="submit" class="mt-8 space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="hidden" aria-hidden="true"><label>Website<input wire:model="website" type="text" tabindex="-1" autocomplete="off"></label></div>
            <section class="space-y-5"><flux:heading size="lg">{{ __('About you') }}</flux:heading><flux:checkbox wire:model.live="form.isAnonymous" :label="__('Submit anonymously')" /><div class="grid gap-5 sm:grid-cols-2"><flux:input wire:model="form.name" :label="__('Name')" :disabled="$form->isAnonymous" /><flux:input wire:model="form.email" type="email" :label="__('Email address')" /><flux:input wire:model="form.phone" type="tel" :label="__('Phone number')" /><flux:input wire:model="form.country" :label="__('Country')" /><flux:input wire:model="form.city" :label="__('City')" /></div><flux:checkbox wire:model.live="form.allowContact" :label="__('The prayer team may contact me for follow-up')" /></section>
            <flux:separator />
            <section class="space-y-5"><flux:heading size="lg">{{ __('Your request') }}</flux:heading><flux:input wire:model="form.subject" :label="__('Subject')" required maxlength="255" /><flux:select wire:model="form.category" :label="__('Category')"><flux:select.option value="">{{ __('General / not specified') }}</flux:select.option>@foreach (PrayerRequestCategory::cases() as $category)<flux:select.option :value="$category->value">{{ $category->label() }}</flux:select.option>@endforeach</flux:select><flux:textarea wire:model="form.request" :label="__('Prayer request')" rows="9" required maxlength="20000" /></section>
            <flux:separator />
            <section class="space-y-5"><flux:heading size="lg">{{ __('Privacy and consent') }}</flux:heading><flux:select wire:model="form.privacyLevel" :label="__('Who may see this request?')">@foreach (PrayerRequestPrivacy::cases() as $privacy)<flux:select.option :value="$privacy->value">{{ $privacy->label() }}</flux:select.option>@endforeach</flux:select><flux:checkbox wire:model.live="form.allowPublication" :label="__('I permit an edited, de-identified version to be considered for publication')" /><flux:text>{{ __('Publication is never automatic. Authorized staff must create and approve a separate version with sensitive information removed.') }}</flux:text><flux:checkbox wire:model="form.termsAccepted" :label="__('I acknowledge the privacy information and consent to submit this request')" /></section>
            <div class="flex justify-end"><flux:button type="submit" variant="primary" icon="paper-airplane" wire:loading.attr="disabled" wire:target="submit"><span wire:loading.remove wire:target="submit">{{ __('Submit prayer request') }}</span><span wire:loading wire:target="submit">{{ __('Submitting securely…') }}</span></flux:button></div>
        </form>
    @endif
    </section>
</div>
