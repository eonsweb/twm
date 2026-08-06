<?php

use App\Actions\ContactSubmissions\SubmitContactSubmission;
use App\ContactSubmissionCategory;
use App\Livewire\Forms\ContactSubmissionForm;
use App\Models\ServiceSchedule;
use App\PreferredContactMethod;
use App\Settings\SettingManager;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.public'), Title('Contact Us')] class extends Component
{
    public ContactSubmissionForm $form;
    public string $website = '';
    public int $formStartedAt;
    public string $submissionToken;
    public ?string $successReference = null;
    /** @var array<string, mixed> */ public array $contact = [];
    /** @var array<string, mixed> */ public array $social = [];
    public $serviceSchedules;

    public function mount(SettingManager $settings): void
    {
        $this->formStartedAt = now()->timestamp; $this->submissionToken = (string) Str::uuid();
        $this->contact = $settings->publicGroup('contact'); $this->social = $settings->publicGroup('social');
        $this->serviceSchedules = ServiceSchedule::query()->where('is_active', true)->orderBy('display_order')->orderBy('id')->get(['id', 'name', 'day_of_week', 'start_time', 'end_time', 'location']);
    }

    public function submit(SubmitContactSubmission $submit): void
    {
        if ($this->website !== '' || now()->timestamp - $this->formStartedAt < 3) { throw ValidationException::withMessages(['form.subject' => __('Please review the form and try again.')]); }
        $this->form->normalize(); $this->form->validate(); $this->form->validateContactMethod();
        $ipKey = 'contact:ip:'.hash('sha256', (string) request()->ip());
        $emailKey = 'contact:email:'.hash('sha256', $this->form->email);
        if (RateLimiter::tooManyAttempts($ipKey, (int) config('contact.rate_limit.ip_per_hour', 8)) || RateLimiter::tooManyAttempts($emailKey, (int) config('contact.rate_limit.email_per_hour', 4))) {
            throw ValidationException::withMessages(['form.email' => __('Too many messages have been submitted. Please try again later.')]);
        }
        $sourcePage = Str::before((string) request()->headers->get('referer', url()->current()), '?');
        $submission = $submit->handle($this->form->data(), $this->submissionToken, ['ip_address' => request()->ip(), 'user_agent' => Str::limit((string) request()->userAgent(), 1000), 'source_page' => Str::limit($sourcePage, 2000)]);
        RateLimiter::increment($ipKey, decaySeconds: 3600); RateLimiter::increment($emailKey, decaySeconds: 3600);
        $this->successReference = $submission->reference_number; $this->form->reset(); $this->form->category = ContactSubmissionCategory::General->value; $this->form->preferredContactMethod = PreferredContactMethod::Email->value;
        $this->submissionToken = (string) Str::uuid(); $this->formStartedAt = now()->timestamp; $this->resetValidation();
    }
};
?>

<main class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
    <header class="max-w-3xl"><p class="font-semibold uppercase tracking-widest text-church-maroon-700 dark:text-church-gold-400">{{ __('Connect with us') }}</p><h1 class="mt-3 text-4xl font-bold tracking-tight sm:text-5xl">{{ __('Contact Triumphant World Ministry') }}</h1><p class="mt-5 text-lg leading-8 text-slate-600 dark:text-zinc-300">{{ __('We welcome your questions, feedback, and enquiries. Prayer requests are handled through our separate confidential prayer form.') }}</p></header>
    <div class="mt-10 grid gap-8 lg:grid-cols-[22rem_minmax(0,1fr)]">
        <aside class="space-y-5"><section class="rounded-2xl bg-church-maroon-950 p-6 text-white shadow-xl"><flux:heading size="lg" class="text-white">{{ __('Church contact') }}</flux:heading><dl class="mt-5 space-y-4 text-sm text-white/80">@if(filled(data_get($contact, 'physical_address')))<div><dt class="font-semibold text-church-gold-300">{{ __('Address') }}</dt><dd class="mt-1 whitespace-pre-line">{{ data_get($contact, 'physical_address') }}</dd></div>@endif @if(filled(data_get($contact, 'primary_phone')))<div><dt class="font-semibold text-church-gold-300">{{ __('Phone') }}</dt><dd class="mt-1"><a href="tel:{{ data_get($contact, 'primary_phone') }}" class="hover:underline">{{ data_get($contact, 'primary_phone') }}</a></dd></div>@endif @if(filled(data_get($contact, 'primary_email')))<div><dt class="font-semibold text-church-gold-300">{{ __('Email') }}</dt><dd class="mt-1 break-all"><a href="mailto:{{ data_get($contact, 'primary_email') }}" class="hover:underline">{{ data_get($contact, 'primary_email') }}</a></dd></div>@endif @if(filled(data_get($contact, 'office_hours')))<div><dt class="font-semibold text-church-gold-300">{{ __('Office hours') }}</dt><dd class="mt-1 whitespace-pre-line">{{ data_get($contact, 'office_hours') }}</dd></div>@endif</dl>@if(filled(data_get($contact, 'map_url')))<flux:button class="mt-6 w-full" :href="data_get($contact, 'map_url')" target="_blank" rel="noopener noreferrer" icon="map-pin">{{ __('Open map') }}</flux:button>@endif</section>
        @if($serviceSchedules->isNotEmpty())<section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900"><flux:heading>{{ __('Service times') }}</flux:heading><div class="mt-4 space-y-3">@foreach($serviceSchedules as $schedule)<div wire:key="contact-service-{{ $schedule->id }}"><p class="font-semibold">{{ $schedule->name }}</p><p class="text-sm text-slate-500">{{ $schedule->day_of_week }} · {{ $schedule->start_time }}@if($schedule->location) · {{ $schedule->location }}@endif</p></div>@endforeach</div></section>@endif
        @php($socialNetworks = collect(['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'x' => 'X', 'tiktok' => 'TikTok', 'linkedin' => 'LinkedIn', 'telegram' => 'Telegram', 'whatsapp_channel' => 'WhatsApp', 'livestream' => 'Livestream', 'podcast' => 'Podcast'])->filter(fn ($label, $network) => data_get($social, $network.'_enabled') && filled(data_get($social, $network.'_url'))))
        @if($socialNetworks->isNotEmpty())<section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900"><flux:heading>{{ __('Follow us') }}</flux:heading><div class="mt-4 flex flex-wrap gap-2">@foreach($socialNetworks as $network => $label)<flux:button size="sm" :href="data_get($social, $network.'_url')" target="_blank" rel="noopener noreferrer" wire:key="contact-social-{{ $network }}">{{ $label }}</flux:button>@endforeach</div></section>@endif
        <flux:callout icon="heart" heading="{{ __('Need prayer or urgent care?') }}"><p>{{ __('Use the confidential Prayer Request page for prayer needs. For emergencies, contact local emergency services directly.') }}</p><flux:button class="mt-3" size="sm" :href="route('prayer-requests.create.public')" wire:navigate>{{ __('Submit a prayer request') }}</flux:button></flux:callout></aside>
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 dark:border-zinc-800 dark:bg-zinc-900" aria-live="polite">
            @if($successReference)<flux:callout variant="success" icon="check-circle" heading="{{ __('Your message has been received') }}"><p>{{ __('Thank you for contacting Triumphant World Ministry. Your reference number is :reference.', ['reference' => $successReference]) }}</p><flux:button class="mt-4" wire:click="$set('successReference', null)">{{ __('Send another message') }}</flux:button></flux:callout>
            @else<form wire:submit="submit" class="space-y-5"><div class="hidden" aria-hidden="true"><label>Website<input wire:model="website" type="text" tabindex="-1" autocomplete="off"></label></div><div class="grid gap-5 sm:grid-cols-2"><flux:input wire:model="form.name" :label="__('Name')" autocomplete="name" required/><flux:input wire:model="form.email" type="email" :label="__('Email address')" autocomplete="email" required/><flux:input wire:model="form.phone" type="tel" :label="__('Phone number')" autocomplete="tel"/><flux:select wire:model="form.category" :label="__('Enquiry category')" required>@foreach(ContactSubmissionCategory::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select></div><flux:input wire:model="form.subject" :label="__('Subject')" required maxlength="200"/><flux:textarea wire:model="form.message" :label="__('Message')" rows="8" required maxlength="5000"/><flux:select wire:model="form.preferredContactMethod" :label="__('Preferred contact method')">@foreach(PreferredContactMethod::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select><flux:checkbox wire:model="form.consent" :label="__('I agree to be contacted regarding this enquiry.')"/><div class="flex justify-end"><flux:button type="submit" variant="primary" icon="paper-airplane" wire:loading.attr="disabled" wire:target="submit"><span wire:loading.remove wire:target="submit">{{ __('Send message') }}</span><span wire:loading wire:target="submit">{{ __('Sending…') }}</span></flux:button></div></form>@endif
        </section>
    </div>
</main>
