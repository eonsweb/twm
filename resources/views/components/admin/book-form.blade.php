@props(['form', 'leaders', 'speakers'])

<div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(20rem,1fr)]">
    <div class="space-y-6">
        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Book details') }}</flux:heading>
            <div class="mt-5 space-y-5">
                <flux:input wire:model.live.debounce.350ms="form.title" :label="__('Title')" required />
                <flux:input wire:model="form.subtitle" :label="__('Subtitle')" />
                <flux:input wire:model.blur="form.slug" wire:change="form.markSlugAsEdited" :label="__('Slug')" required />
                <flux:textarea wire:model="form.shortDescription" :label="__('Short description')" rows="3" maxlength="1000" />
                <flux:textarea wire:model="form.description" :label="__('Full description')" rows="12" />
            </div>
        </section>

        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Author') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Enter an author manually, or link one existing leader or sermon speaker. Linked selections update the public author name, which remains editable.') }}</flux:text>
            <div class="mt-5 space-y-5">
                <flux:input wire:model="form.authorName" :label="__('Display author name')" required />
                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:select wire:model.live="form.leadershipId" :label="__('Leadership profile')">
                        <flux:select.option value="">{{ __('No linked leader') }}</flux:select.option>
                        @foreach ($leaders as $leader)
                            <flux:select.option :value="$leader->id">{{ $leader->full_name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="form.speakerId" :label="__('Sermon speaker')">
                        <flux:select.option value="">{{ __('No linked speaker') }}</flux:select.option>
                        @foreach ($speakers as $speaker)
                            <flux:select.option :value="$speaker->id">{{ $speaker->full_name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Publication metadata') }}</flux:heading>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="form.isbn" :label="__('ISBN')" />
                <flux:input wire:model="form.publisher" :label="__('Publisher')" />
                <flux:input wire:model="form.publicationDate" :label="__('Publication date')" type="date" />
                <flux:input wire:model="form.edition" :label="__('Edition')" />
                <flux:input wire:model="form.language" :label="__('Language')" required />
                <flux:input wire:model="form.pageCount" :label="__('Page count')" type="number" min="1" />
            </div>
        </section>

        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Cover image') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Choose an active public image from the Media Library, or upload one there without duplicating file storage.') }}</flux:text>
            <div class="mt-5">
                <livewire:media-picker
                    wire:model.deep="form.mediaIds"
                    :allowed-types="[\App\MediaType::Image->value]"
                    :multiple="false"
                    :maximum="1"
                    collection="book-cover"
                />
                <flux:error name="form.mediaIds" />
            </div>
        </section>

        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Book audio sample') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Optionally choose one active public audio file from the Media Library for visitors to preview.') }}</flux:text>
            <div class="mt-5">
                <livewire:media-picker
                    wire:model.deep="form.audioSampleMediaIds"
                    :allowed-types="[\App\MediaType::Audio->value]"
                    :multiple="false"
                    :maximum="1"
                    collection="book-audio-sample"
                />
                <flux:error name="form.audioSampleMediaIds" />
                <flux:error name="form.audioSampleMediaIds.*" />
            </div>
        </section>
    </div>

    <div class="space-y-6">
        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Publishing') }}</flux:heading>
            <div class="mt-5 space-y-5">
                <flux:select wire:model.live="form.status" :label="__('Status')">
                    @foreach (\App\BookStatus::cases() as $status)
                        <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.publishedAt" :label="__('Publish date and time')" type="datetime-local" />
                <flux:switch wire:model="form.isFeatured" :label="__('Feature this book')" :disabled="$form->status !== \App\BookStatus::Published->value" />
            </div>
        </section>

        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Format and availability') }}</flux:heading>
            <div class="mt-5 space-y-5">
                <flux:select wire:model.live="form.format" :label="__('Format')">
                    @foreach (\App\BookFormat::cases() as $format)
                        <flux:select.option :value="$format->value">{{ $format->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="form.availabilityStatus" :label="__('Availability')">
                    @foreach (\App\BookAvailabilityStatus::cases() as $availability)
                        <flux:select.option :value="$availability->value">{{ $availability->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.stockQuantity" :label="__('Stock quantity')" type="number" min="0" />
            </div>
        </section>

        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Pricing and access') }}</flux:heading>
            <div class="mt-5 space-y-5">
                <flux:switch wire:model.live="form.isFree" :label="__('Free book')" />
                <div class="grid grid-cols-[minmax(0,1fr)_6rem] gap-3">
                    <flux:input wire:model="form.price" :label="__('Price')" type="number" min="0" step="0.01" :disabled="$form->isFree" />
                    <flux:input wire:model="form.currency" :label="__('Currency')" maxlength="3" required />
                </div>
                <flux:field>
                    <flux:label>{{ __('Purchase URL (Optional)') }}</flux:label>
                    <flux:input wire:model="form.purchaseUrl" type="url" />
                    <flux:description>{{ __("Leave blank to use the book's public page automatically.") }}</flux:description>
                    <flux:error name="form.purchaseUrl" />
                </flux:field>
                <flux:input wire:model="form.downloadUrl" :label="__('Download URL')" type="url" />
            </div>
        </section>
    </div>
</div>
