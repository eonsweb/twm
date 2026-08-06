@props(['form', 'parents' => collect(), 'featuredMediaIds' => [], 'ogMediaIds' => []])
<div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(19rem,1fr)]">
    <div class="space-y-6">
        <flux:card class="space-y-5">
            <flux:heading size="lg">{{ __('Page content') }}</flux:heading>
            <flux:input wire:model.live.debounce.400ms="form.title" :label="__('Title')" required />
            <flux:input wire:model.blur="form.slug" wire:change="form.markSlugAsEdited" :label="__('Slug')" required description="{{ __('Public URL: /'.($form->slug ?: 'page-slug')) }}" />
            <div class="grid gap-5 sm:grid-cols-2">
                <flux:select wire:model="form.pageType" :label="__('Page type')">@foreach (\App\PageType::cases() as $type)<flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>@endforeach</flux:select>
                <flux:select wire:model="form.template" :label="__('Template')">@foreach (\App\PageTemplate::cases() as $template)<flux:select.option :value="$template->value">{{ $template->label() }}</flux:select.option>@endforeach</flux:select>
            </div>
            <flux:textarea wire:model="form.excerpt" :label="__('Excerpt')" rows="3" />
            <flux:textarea wire:model="form.content" :label="__('Main content')" rows="14" description="{{ __('Supported formatting is sanitized before it is stored.') }}" />
        </flux:card>
        <flux:card class="space-y-5">
            <flux:heading size="lg">{{ __('Navigation and hierarchy') }}</flux:heading>
            <div class="grid gap-5 sm:grid-cols-2">
                <flux:select wire:model="form.parentId" :label="__('Parent page')"><flux:select.option value="">{{ __('None') }}</flux:select.option>@foreach ($parents as $parent)<flux:select.option :value="$parent->id" wire:key="parent-{{ $parent->id }}">{{ $parent->title }}</flux:select.option>@endforeach</flux:select>
                <flux:input wire:model="form.navigationLabel" :label="__('Navigation label')" />
                <flux:input wire:model="form.navigationOrder" type="number" min="0" :label="__('Navigation order')" />
            </div>
            <flux:switch wire:model="form.showInNavigation" :label="__('Show in navigation')" />
        </flux:card>
        @can(\App\PermissionName::PagesManageSeo->value)
            <flux:card class="space-y-5">
                <flux:heading size="lg">{{ __('Search and social') }}</flux:heading>
                <flux:input wire:model="form.metaTitle" :label="__('Meta title')" /><p class="text-xs text-slate-500">{{ mb_strlen($form->metaTitle) }}/60 recommended</p>
                <flux:textarea wire:model="form.metaDescription" :label="__('Meta description')" rows="3" /><p class="text-xs text-slate-500">{{ mb_strlen($form->metaDescription) }}/160 recommended</p>
                <flux:input wire:model="form.metaKeywords" :label="__('Meta keywords')" />
                <flux:input wire:model="form.canonicalUrl" type="url" :label="__('Canonical URL')" />
                <div class="flex flex-wrap gap-5"><flux:switch wire:model="form.robotsIndex" :label="__('Allow indexing')" /><flux:switch wire:model="form.robotsFollow" :label="__('Follow links')" /></div>
                <flux:input wire:model="form.ogTitle" :label="__('Open Graph title')" />
                <flux:textarea wire:model="form.ogDescription" :label="__('Open Graph description')" rows="3" />
                <livewire:media-picker wire:model="ogMediaIds" :allowed-types="['image']" collection="page-og" />
            </flux:card>
        @endcan
    </div>
    <div class="space-y-6">
        <flux:card class="space-y-5">
            <flux:heading size="lg">{{ __('Publication') }}</flux:heading>
            <flux:select wire:model.live="form.status" :label="__('Status')">@foreach (\App\PageStatus::cases() as $status)<flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>@endforeach</flux:select>
            <flux:select wire:model="form.visibility" :label="__('Visibility')">@foreach (\App\PageVisibility::cases() as $visibility)<flux:select.option :value="$visibility->value">{{ $visibility->label() }}</flux:select.option>@endforeach</flux:select>
            @if (in_array($form->status, ['published','scheduled'], true))<flux:input wire:model="form.publishedAt" type="datetime-local" :label="$form->status === 'scheduled' ? __('Schedule for') : __('Published at')" required />@endif
            <flux:switch wire:model="form.isHomepage" :label="__('Use as homepage')" />
        </flux:card>
        <flux:card class="space-y-4"><flux:heading size="lg">{{ __('Featured image') }}</flux:heading><livewire:media-picker wire:model="featuredMediaIds" :allowed-types="['image']" collection="page-featured" /></flux:card>
    </div>
</div>
