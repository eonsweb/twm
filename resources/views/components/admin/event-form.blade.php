@props([
    'form',
    'eventTypes',
    'ministries' => [],
    'currentImageUrl' => null,
])

<div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(20rem,1fr)]">
    <div class="space-y-6">
        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Basic information') }}</flux:heading>
            <div class="mt-5 space-y-5">
                <flux:input wire:model="form.title" :label="__('Event title')" required />
                <flux:select wire:model="form.eventTypeId" :label="__('Event type')">
                    <flux:select.option value="">{{ __('No event type') }}</flux:select.option>
                    @foreach ($eventTypes as $eventType)
                        <flux:select.option :value="$eventType->id" wire:key="event-type-{{ $eventType->id }}">{{ $eventType->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="form.ministryId" :label="__('Organizing ministry')">
                    <flux:select.option value="">{{ __('No organizing ministry') }}</flux:select.option>
                    @foreach ($ministries as $ministry)
                        <flux:select.option :value="$ministry->id" wire:key="event-ministry-{{ $ministry->id }}">{{ $ministry->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:textarea wire:model="form.shortDescription" :label="__('Short description')" rows="3" maxlength="2000" />
                <flux:textarea wire:model="form.description" :label="__('Full description')" rows="12" description="{{ __('Plain text is displayed safely on the public event page.') }}" />
                <flux:input wire:model="form.featuredImage" :label="__('Featured image')" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" description="{{ __('JPG, PNG, or WebP. Maximum 5 MB.') }}" />
                @if ($currentImageUrl || $form->featuredImage)
                    <div class="flex flex-wrap items-center gap-4 rounded-lg bg-slate-50 p-3 dark:bg-zinc-800">
                        @if ($form->featuredImage && str_starts_with((string) $form->featuredImage->getMimeType(), 'image/'))
                            <img src="{{ $form->featuredImage->temporaryUrl() }}" alt="{{ __('New event image preview') }}" class="h-28 w-44 rounded-lg object-cover">
                        @elseif ($currentImageUrl)
                            <img src="{{ $currentImageUrl }}" alt="{{ __('Current event image') }}" class="h-28 w-44 rounded-lg object-cover">
                        @endif
                        @if ($currentImageUrl)
                            <flux:checkbox wire:model="form.removeFeaturedImage" :label="__('Remove current image')" />
                        @endif
                    </div>
                @endif
            </div>
        </section>

        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Date and time') }}</flux:heading>
            <div class="mt-5 space-y-5">
                <flux:switch wire:model.live="form.isAllDay" :label="__('All-day event')" />
                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:input wire:model="form.startDate" :label="__('Start date')" type="date" required />
                    @unless ($form->isAllDay)
                        <flux:input wire:model="form.startTime" :label="__('Start time')" type="time" required />
                    @endunless
                    <flux:input wire:model="form.endDate" :label="__('End date')" type="date" />
                    @if (! $form->isAllDay && $form->endDate !== '')
                        <flux:input wire:model="form.endTime" :label="__('End time')" type="time" required />
                    @endif
                </div>
                <flux:select wire:model="form.timezone" :label="__('Timezone')">
                    @foreach (['Africa/Accra', 'UTC', 'Europe/London', 'America/New_York'] as $timezone)
                        <flux:select.option :value="$timezone">{{ $timezone }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:switch wire:model.live="form.isRecurring" :label="__('Recurring event')" />
                @if ($form->isRecurring)
                    <flux:select wire:model.live="form.recurrence" :label="__('Recurrence')">
                        <flux:select.option value="">{{ __('Choose a recurrence') }}</flux:select.option>
                        @foreach (\App\EventRecurrence::cases() as $recurrence)
                            <flux:select.option :value="$recurrence->value">{{ $recurrence->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @if ($form->recurrence === \App\EventRecurrence::Custom->value)
                        <flux:textarea wire:model="form.customRecurrence" :label="__('Custom recurrence rule')" rows="3" placeholder="{{ __('For example: Every second Saturday of the month') }}" />
                    @endif
                @endif
            </div>
        </section>

        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Location') }}</flux:heading>
            <div class="mt-5 space-y-5">
                <flux:radio.group wire:model.live="form.locationType" :label="__('Event format')" variant="segmented">
                    @foreach (\App\EventLocationType::cases() as $locationType)
                        <flux:radio :value="$locationType->value" :label="$locationType->label()" />
                    @endforeach
                </flux:radio.group>
                @if (in_array($form->locationType, ['physical', 'hybrid'], true))
                    <div class="grid gap-5 sm:grid-cols-2">
                        <flux:input wire:model="form.venueName" :label="__('Venue name')" required />
                        <flux:input wire:model="form.address" :label="__('Address')" />
                        <flux:input wire:model="form.city" :label="__('City')" />
                        <flux:input wire:model="form.region" :label="__('Region')" />
                        <flux:input wire:model="form.country" :label="__('Country')" required />
                        <flux:input wire:model="form.locationUrl" :label="__('Map or location URL')" type="url" />
                    </div>
                @endif
                @if (in_array($form->locationType, ['online', 'hybrid'], true))
                    <flux:input wire:model="form.meetingUrl" :label="__('Online meeting or livestream URL')" type="url" required />
                @endif
            </div>
        </section>

        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Registration and contact') }}</flux:heading>
            <div class="mt-5 space-y-5">
                <flux:switch wire:model.live="form.registrationRequired" :label="__('Registration required')" />
                @if ($form->registrationRequired)
                    <div class="grid gap-5 sm:grid-cols-2">
                        <flux:input wire:model="form.registrationUrl" :label="__('Registration URL')" type="url" required />
                        <flux:input wire:model="form.registrationDeadline" :label="__('Registration deadline')" type="datetime-local" />
                        <flux:input wire:model="form.maximumAttendees" :label="__('Maximum attendees')" type="number" min="1" />
                    </div>
                @endif
                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:input wire:model="form.contactName" :label="__('Contact person')" />
                    <flux:input wire:model="form.contactPhone" :label="__('Contact phone')" type="tel" />
                    <flux:input wire:model="form.contactEmail" :label="__('Contact email')" type="email" />
                </div>
            </div>
        </section>
    </div>

    <div class="space-y-6">
        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Publishing') }}</flux:heading>
            <div class="mt-5 space-y-5">
                <flux:select wire:model.live="form.status" :label="__('Status')">
                    @foreach (\App\EventStatus::cases() as $status)
                        <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                @if ($form->status === \App\EventStatus::Scheduled->value)
                    <flux:input wire:model="form.publishedAt" :label="__('Publish date and time')" type="datetime-local" required />
                @endif
                <flux:switch wire:model="form.isFeatured" :label="__('Featured event')" />
                <flux:switch wire:model="form.isLivestreamed" :label="__('Livestreamed event')" />
                <flux:callout icon="eye">
                    {{ __('Use Preview after saving to verify public presentation. Draft and scheduled events remain private.') }}
                </flux:callout>
            </div>
        </section>
    </div>
</div>
