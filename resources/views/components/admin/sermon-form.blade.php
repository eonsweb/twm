@props([
    'form',
    'speakers',
    'series',
    'topics',
    'ministries' => [],
    'currentThumbnailUrl' => null,
])

@php
    $canPreviewEmbed = filled($form->embedUrl)
        && app(\App\Sermons\ExternalMedia::class)->isEmbeddableUrl($form->embedUrl);
@endphp

<div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(20rem,1fr)]">
    <div class="space-y-6">
        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Sermon details') }}</flux:heading>
            <div class="mt-5 space-y-5">
                <flux:input wire:model.live.debounce.400ms="form.title" :label="__('Title')" required />
                <flux:input wire:model="form.slug" wire:change="form.markSlugAsEdited" :label="__('URL slug')" description="{{ __('Generated from the title. Published slugs remain unchanged unless edited here.') }}" required />
                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:select wire:model="form.speakerId" :label="__('Speaker')" required>
                        <flux:select.option value="">{{ __('Choose a speaker') }}</flux:select.option>
                        @foreach ($speakers as $speaker)
                            <flux:select.option :value="$speaker->id" wire:key="speaker-{{ $speaker->id }}">{{ $speaker->full_name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="form.sermonSeriesId" :label="__('Series')">
                        <flux:select.option value="">{{ __('No series') }}</flux:select.option>
                        @foreach ($series as $sermonSeries)
                            <flux:select.option :value="$sermonSeries->id" wire:key="series-{{ $sermonSeries->id }}">{{ $sermonSeries->title }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="form.sermonDate" :label="__('Sermon date')" type="date" required />
                    <flux:input wire:model="form.duration" :label="__('Duration')" placeholder="45:30" description="{{ __('Use MM:SS or HH:MM:SS.') }}" />
                    <flux:input wire:model="form.serviceName" :label="__('Service name')" />
                    <flux:input wire:model="form.location" :label="__('Location or branch')" />
                </div>
                <flux:input wire:model="form.scriptureReference" :label="__('Scripture reference')" placeholder="Romans 8:28" />
                <flux:textarea wire:model="form.summary" :label="__('Summary')" rows="4" />
                <flux:textarea wire:model="form.description" :label="__('Sermon notes')" rows="12" description="{{ __('Plain text only. Media embeds and HTML are not accepted.') }}" />
                <flux:field>
                    <flux:label>{{ __('Topics') }}</flux:label>
                    <div class="grid gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-2 dark:border-zinc-700">
                        @forelse ($topics as $topic)
                            <flux:checkbox wire:model="form.topicIds" :value="$topic->id" :label="$topic->name" wire:key="topic-{{ $topic->id }}" />
                        @empty
                            <flux:text>{{ __('No topics have been created yet.') }}</flux:text>
                        @endforelse
                    </div>
                    <flux:error name="form.topicIds" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Related ministries') }}</flux:label>
                    <div class="grid gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-2 dark:border-zinc-700">
                        @foreach ($ministries as $ministry)
                            <flux:checkbox wire:model="form.ministryIds" :value="$ministry->id" :label="$ministry->name" wire:key="sermon-ministry-{{ $ministry->id }}" />
                        @endforeach
                    </div>
                </flux:field>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('External media') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Enter an HTTPS page URL. Never paste iframe or script code.') }}</flux:text>
            <div class="mt-5 space-y-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:select wire:model="form.mediaType" :label="__('Media type')">
                        @foreach (\App\SermonMediaType::cases() as $type)
                            <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input :value="\App\SermonMediaPlatform::tryFrom($form->mediaPlatform)?->label() ?? __('Other')" :label="__('Detected platform')" readonly />
                </div>
                <flux:input wire:model.blur="form.externalMediaUrl" wire:blur="inspectMedia" :label="__('External media URL')" type="url" required />
                <div wire:loading wire:target="inspectMedia" class="text-sm text-church-maroon-700 dark:text-church-gold-400">{{ __('Checking media URL...') }}</div>
                @if ($canPreviewEmbed)
                    <div class="aspect-video overflow-hidden rounded-xl bg-slate-950">
                        <iframe src="{{ $form->embedUrl }}" title="{{ __('Media preview') }}" class="h-full w-full border-0" sandbox="allow-scripts allow-same-origin allow-presentation" referrerpolicy="no-referrer"></iframe>
                    </div>
                @elseif ($form->externalMediaUrl)
                    <flux:callout icon="arrow-top-right-on-square">{{ __('This host will use a safe external-link fallback instead of an iframe.') }}</flux:callout>
                @endif
                <flux:input wire:model="form.thumbnail" :label="__('Custom thumbnail')" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" description="{{ __('JPG, PNG, or WebP. Maximum 4 MB.') }}" />
                @if ($currentThumbnailUrl || $form->thumbnail)
                    <div class="flex items-center gap-4 rounded-lg bg-slate-50 p-3 dark:bg-zinc-800">
                        @if ($form->thumbnail)
                            <img src="{{ $form->thumbnail->temporaryUrl() }}" alt="" class="h-20 w-32 rounded-lg object-cover">
                        @elseif ($currentThumbnailUrl)
                            <img src="{{ $currentThumbnailUrl }}" alt="" class="h-20 w-32 rounded-lg object-cover">
                        @endif
                        @if ($currentThumbnailUrl)
                            <flux:checkbox wire:model="form.removeThumbnail" :label="__('Remove custom thumbnail')" />
                        @endif
                    </div>
                @endif
            </div>
        </section>
    </div>

    <div class="space-y-6">
        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Publication') }}</flux:heading>
            <div class="mt-5 space-y-5">
                <flux:select wire:model.live="form.status" :label="__('Status')">
                    @foreach (\App\SermonStatus::cases() as $status)
                        <flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                @if ($form->status === \App\SermonStatus::Published->value)
                    <flux:input wire:model="form.publishedAt" :label="__('Publish at')" type="datetime-local" required />
                @endif
                @if ($form->status === \App\SermonStatus::Scheduled->value)
                    <flux:input wire:model="form.scheduledAt" :label="__('Scheduled for')" type="datetime-local" required />
                @endif
                <flux:switch wire:model="form.isFeatured" :label="__('Featured sermon')" />
                <flux:input wire:model="form.displayOrder" :label="__('Display order')" type="number" min="0" />
            </div>
        </section>

        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Search and sharing') }}</flux:heading>
            <div class="mt-5 space-y-5">
                <flux:input wire:model="form.seoTitle" :label="__('SEO title')" maxlength="70" />
                <flux:textarea wire:model="form.seoDescription" :label="__('SEO description')" rows="4" maxlength="170" />
            </div>
        </section>
    </div>
</div>
