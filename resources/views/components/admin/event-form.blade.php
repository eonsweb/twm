@props([
    'form',
    'eventTypes',
    'ministries' => [],
    'currentImageUrl' => null,
])

@php
    $icons = \App\Support\EventIcons::options();
    $weekdays = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
@endphp

<div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(20rem,1fr)]">
    <div class="space-y-6">
        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Event information') }}</flux:heading>
            <div class="mt-5 space-y-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="form.title" :label="__('Event title')" required />
                    <flux:input wire:model="form.slug" :label="__('Slug')" :placeholder="__('Generated from the title')" description="{{ __('Leave blank to generate automatically.') }}" />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
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
                </div>
                <flux:textarea wire:model="form.shortDescription" :label="__('Short description')" rows="3" maxlength="2000" />
                <flux:textarea wire:model="form.description" :label="__('Full description')" rows="10" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model="form.icon" :label="__('Event icon')" description="{{ __('Overrides the event type icon.') }}">
                        <flux:select.option value="">{{ __('Use event type icon') }}</flux:select.option>
                        @foreach ($icons as $value => $label)
                            <flux:select.option :value="$value" wire:key="event-icon-{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <div>
                        <flux:field>
                            <flux:label>{{ __('Featured image') }}</flux:label>
                            <flux:description>{{ __('Choose an image from the Media Library.') }}</flux:description>
                            <livewire:media-picker wire:model.deep="form.featuredImageIds" :allowed-types="[\App\MediaType::Image->value]" collection="event-featured" :multiple="false" :maximum="1" />
                            <flux:error name="form.featuredImageIds" />
                        </flux:field>
                        @if (! $form->removeFeaturedImage && $currentImageUrl && $form->featuredImageIds === [])
                            <img src="{{ $currentImageUrl }}" alt="{{ __('Current event image') }}" class="mt-3 h-24 w-40 rounded-lg object-cover">
                        @endif
                        @if (! $form->removeFeaturedImage && ($currentImageUrl || $form->featuredImageIds !== []))
                            <flux:button type="button" size="sm" variant="ghost" icon="trash" wire:click="removeEventImage" class="mt-3">
                                {{ __('Remove image') }}
                            </flux:button>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Schedule') }}</flux:heading>
            <div class="mt-5 space-y-5">
                <flux:radio.group wire:model.live="form.scheduleType" :label="__('Schedule type')" variant="cards" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    @foreach (\App\EventScheduleType::cases() as $type)
                        <flux:radio :value="$type->value" :label="$type->label()" wire:key="schedule-type-{{ $type->value }}" />
                    @endforeach
                </flux:radio.group>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <flux:input wire:model="form.startDate" :label="__('Anchor / start date')" type="date" required />
                    @unless ($form->isAllDay)<flux:input wire:model="form.startTime" :label="__('Start time')" type="time" required />@endunless
                    @if ($form->scheduleType === 'one_time')
                        <flux:input wire:model="form.endDate" :label="__('End date')" type="date" />
                    @endif
                    @unless ($form->isAllDay)<flux:input wire:model="form.endTime" :label="__('End time')" type="time" />@endunless
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="form.timezone" :label="__('Timezone')" required />
                    <div class="pt-7"><flux:switch wire:model.live="form.isAllDay" :label="__('All day')" /></div>
                </div>

                @if ($form->scheduleType === 'weekly')
                    <flux:checkbox.group wire:model="form.recurrenceDays" :label="__('Weekdays')" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($weekdays as $day)<flux:checkbox :value="$day" :label="str($day)->title()" wire:key="weekly-day-{{ $day }}" />@endforeach
                    </flux:checkbox.group>
                @elseif ($form->scheduleType === 'monthly')
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:select wire:model.live="form.recurrenceWeekOfMonth" :label="__('Week of month')">
                            <flux:select.option value="">{{ __('Use an exact day of month') }}</flux:select.option>
                            @foreach (['1' => 'First week', '2' => 'Second week', '3' => 'Third week', '4' => 'Fourth week', 'last' => 'Last week'] as $value => $label)<flux:select.option :value="$value">{{ __($label) }}</flux:select.option>@endforeach
                        </flux:select>
                        @if ($form->recurrenceWeekOfMonth === '')
                            <flux:input wire:model="form.recurrenceDayOfMonth" :label="__('Day of month')" type="number" min="1" max="31" />
                        @endif
                    </div>
                    @if ($form->recurrenceWeekOfMonth !== '')
                        <flux:checkbox.group wire:model="form.recurrenceDays" :label="__('Weekday(s)')" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ($weekdays as $day)<flux:checkbox :value="$day" :label="str($day)->title()" wire:key="monthly-day-{{ $day }}" />@endforeach
                        </flux:checkbox.group>
                    @endif
                @elseif ($form->scheduleType === 'yearly')
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:select wire:model="form.recurrenceMonth" :label="__('Month')">
                            <flux:select.option value="">{{ __('Choose month') }}</flux:select.option>
                            @foreach (range(1, 12) as $month)<flux:select.option :value="$month">{{ \Illuminate\Support\Carbon::create()->month($month)->format('F') }}</flux:select.option>@endforeach
                        </flux:select>
                        <flux:input wire:model="form.recurrenceDayOfMonth" :label="__('Day of month (optional)')" type="number" min="1" max="31" />
                    </div>
                @elseif ($form->scheduleType === 'custom')
                    <flux:textarea wire:model="form.customRecurrence" :label="__('Custom schedule description')" rows="3" description="{{ __('Use this for irregular schedules; create one-time editions when exact dates change.') }}" />
                @endif

                @if ($form->scheduleType !== 'one_time')
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="form.recurrenceInterval" :label="__('Repeat interval')" type="number" min="1" max="52" />
                        <flux:input wire:model="form.recurrenceEndDate" :label="__('Recurrence end date (optional)')" type="date" />
                    </div>
                @endif
            </div>
        </section>

        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Location and online access') }}</flux:heading>
            <div class="mt-5 space-y-5">
                <flux:radio.group wire:model.live="form.locationType" :label="__('Location type')" variant="segmented">
                    @foreach (\App\EventLocationType::cases() as $type)<flux:radio :value="$type->value" :label="$type->label()" />@endforeach
                </flux:radio.group>
                @if (in_array($form->locationType, ['physical', 'hybrid'], true))
                    <div class="grid gap-4 sm:grid-cols-2"><flux:input wire:model="form.venueName" :label="__('Location name')" /><flux:input wire:model="form.address" :label="__('Address')" /></div>
                    <div class="grid gap-4 sm:grid-cols-3"><flux:input wire:model="form.city" :label="__('City')" /><flux:input wire:model="form.region" :label="__('Region')" /><flux:input wire:model="form.country" :label="__('Country')" /></div>
                    <flux:input wire:model="form.locationUrl" :label="__('Map URL')" type="url" />
                @endif
                @if (in_array($form->locationType, ['online', 'hybrid'], true))<flux:input wire:model="form.meetingUrl" :label="__('Online meeting URL')" type="url" />@endif
                <div class="grid gap-4 sm:grid-cols-2"><flux:input wire:model="form.livestreamUrl" :label="__('Livestream URL')" type="url" /><flux:input wire:model="form.registrationUrl" :label="__('Registration URL')" type="url" /></div>
            </div>
        </section>
    </div>

    <aside class="space-y-6">
        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Publishing') }}</flux:heading>
            <div class="mt-5 space-y-5">
                <flux:select wire:model.live="form.status" :label="__('Status')">@foreach (\App\EventStatus::cases() as $status)<flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>@endforeach</flux:select>
                @if (in_array($form->status, ['scheduled', 'published'], true))<flux:input wire:model="form.publishedAt" :label="__('Published at')" type="datetime-local" />@endif
                <flux:switch wire:model="form.isActive" :label="__('Active')" />
                <flux:switch wire:model="form.isFeatured" :label="__('Featured')" />
                <flux:switch wire:model="form.isLivestreamed" :label="__('Livestreamed')" />
                <flux:input wire:model="form.sortOrder" :label="__('Sort order')" type="number" min="0" />
            </div>
        </section>
        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Registration and contact') }}</flux:heading>
            <div class="mt-5 space-y-5">
                <flux:switch wire:model.live="form.registrationRequired" :label="__('Registration required')" />
                @if ($form->registrationRequired)
                    <flux:input wire:model="form.registrationDeadline" :label="__('Registration deadline')" type="datetime-local" />
                    <flux:input wire:model="form.maximumAttendees" :label="__('Maximum attendees')" type="number" min="1" />
                @endif
                <flux:input wire:model="form.contactName" :label="__('Contact name')" />
                <flux:input wire:model="form.contactPhone" :label="__('Contact phone')" />
                <flux:input wire:model="form.contactEmail" :label="__('Contact email')" type="email" />
            </div>
        </section>
    </aside>
</div>
