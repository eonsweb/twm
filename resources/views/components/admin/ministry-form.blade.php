@props([
    'form',
    'people',
    'sermons',
    'events',
    'currentImageUrl' => null,
    'currentLogoUrl' => null,
])

<div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(20rem,1fr)]">
    <div class="space-y-6">
        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Basic information') }}</flux:heading>
            <div class="mt-5 space-y-5">
                <flux:input wire:model.live.debounce.350ms="form.name" :label="__('Name')" required />
                <flux:input wire:model.blur="form.slug" wire:change="form.markSlugAsEdited" :label="__('Slug')" required />
                <flux:textarea wire:model="form.shortDescription" :label="__('Short description')" rows="3" maxlength="500" />
                <flux:textarea wire:model="form.description" :label="__('Full description')" rows="10" />
            </div>
        </section>

        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Purpose and identity') }}</flux:heading>
            <div class="mt-5 space-y-5">
                <flux:textarea wire:model="form.mission" :label="__('Mission')" rows="4" />
                <flux:textarea wire:model="form.vision" :label="__('Vision')" rows="4" />
                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Ministry logo') }}</flux:label>
                        <flux:input wire:model="form.logo" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" />
                        <flux:error name="form.logo" />
                        @if ($form->logo || $currentLogoUrl)
                            <img src="{{ $form->logo ? $form->logo->temporaryUrl() : $currentLogoUrl }}" alt="{{ __('Ministry logo preview') }}" class="mt-3 size-24 rounded-xl object-cover">
                            @if ($currentLogoUrl)<flux:checkbox wire:model="form.removeLogo" :label="__('Remove current logo')" />@endif
                        @endif
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Featured image') }}</flux:label>
                        <flux:input wire:model="form.featuredImage" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" />
                        <flux:error name="form.featuredImage" />
                        @if ($form->featuredImage || $currentImageUrl)
                            <img src="{{ $form->featuredImage ? $form->featuredImage->temporaryUrl() : $currentImageUrl }}" alt="{{ __('Featured image preview') }}" class="mt-3 h-28 w-full rounded-xl object-cover">
                            @if ($currentImageUrl)<flux:checkbox wire:model="form.removeFeaturedImage" :label="__('Remove current image')" />@endif
                        @endif
                    </flux:field>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between gap-4">
                <div><flux:heading size="lg">{{ __('Leadership') }}</flux:heading><flux:text>{{ __('Assign existing leadership profiles and one optional primary leader.') }}</flux:text></div>
                <flux:button type="button" icon="plus" wire:click="form.addLeader">{{ __('Add leader') }}</flux:button>
            </div>
            <div class="mt-5 space-y-3">
                @foreach ($form->leaders as $index => $leader)
                    <div class="grid gap-3 rounded-xl border border-slate-200 p-4 sm:grid-cols-12 dark:border-zinc-700" wire:key="ministry-leader-{{ $index }}">
                        <div class="sm:col-span-4">
                            <flux:select wire:model="form.leaders.{{ $index }}.person_id" :label="__('Leader')">
                                <flux:select.option value="">{{ __('Choose leader') }}</flux:select.option>
                                @foreach ($people as $person)<flux:select.option :value="$person->id">{{ $person->full_name }}</flux:select.option>@endforeach
                            </flux:select>
                        </div>
                        <div class="sm:col-span-4"><flux:input wire:model="form.leaders.{{ $index }}.role_title" :label="__('Role title')" /></div>
                        <div class="sm:col-span-2"><flux:input wire:model="form.leaders.{{ $index }}.display_order" :label="__('Order')" type="number" min="0" /></div>
                        <div class="flex items-end gap-3 sm:col-span-2">
                            <flux:checkbox wire:model="form.leaders.{{ $index }}.is_primary" :label="__('Primary')" />
                            <flux:button type="button" variant="danger" icon="trash" wire:click="form.removeLeader({{ $index }})" aria-label="{{ __('Remove leader') }}" />
                        </div>
                    </div>
                @endforeach
                <flux:error name="form.leaders" />
            </div>
        </section>

        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Related content') }}</flux:heading>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Sermons') }}</flux:label>
                    <div class="max-h-64 space-y-2 overflow-y-auto rounded-lg border border-slate-200 p-3 dark:border-zinc-700">
                        @foreach ($sermons as $sermon)<flux:checkbox wire:model="form.sermonIds" :value="$sermon->id" :label="$sermon->title" wire:key="related-sermon-{{ $sermon->id }}" />@endforeach
                    </div>
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Events') }}</flux:label>
                    <div class="max-h-64 space-y-2 overflow-y-auto rounded-lg border border-slate-200 p-3 dark:border-zinc-700">
                        @foreach ($events as $event)<flux:checkbox wire:model="form.eventIds" :value="$event->id" :label="$event->title" wire:key="related-event-{{ $event->id }}" />@endforeach
                    </div>
                </flux:field>
            </div>
        </section>
    </div>

    <div class="space-y-6">
        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Publishing') }}</flux:heading>
            <div class="mt-5 space-y-5">
                <flux:select wire:model.live="form.status" :label="__('Status')">
                    @foreach (\App\MinistryStatus::cases() as $status)<flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>@endforeach
                </flux:select>
                <flux:input wire:model="form.publishedAt" :label="__('Publication date and time')" type="datetime-local" />
                <flux:switch wire:model="form.isFeatured" :label="__('Featured ministry')" />
                <flux:input wire:model="form.displayOrder" :label="__('Display order')" type="number" min="0" />
            </div>
        </section>
        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Meeting information') }}</flux:heading>
            <div class="mt-5 space-y-5">
                <flux:input wire:model="form.meetingDay" :label="__('Meeting day')" />
                <flux:input wire:model="form.meetingTime" :label="__('Meeting time')" type="time" />
                <flux:input wire:model="form.meetingLocation" :label="__('Meeting location')" />
            </div>
        </section>
        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Contact information') }}</flux:heading>
            <div class="mt-5 space-y-5">
                <flux:input wire:model="form.contactEmail" :label="__('Email')" type="email" />
                <flux:input wire:model="form.contactPhone" :label="__('Phone')" type="tel" />
            </div>
        </section>
    </div>
</div>
