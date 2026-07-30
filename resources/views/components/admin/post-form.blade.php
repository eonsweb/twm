@props(['form', 'categories', 'tags', 'authors', 'canManageAuthors' => false, 'currentImageUrl' => null])

<div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(20rem,1fr)]">
    <div class="space-y-6">
        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Main content') }}</flux:heading>
            <div class="mt-5 space-y-5">
                <flux:input wire:model.live.debounce.400ms="form.title" :label="__('Title')" required />
                <flux:input wire:model.blur="form.slug" wire:change="form.markSlugAsEdited" :label="__('URL slug')" required />
                <flux:textarea wire:model="form.excerpt" :label="__('Excerpt')" rows="3" maxlength="1000" />
                <flux:field>
                    <flux:label>{{ __('Article content') }}</flux:label>
                    <flux:description>{{ __('Format the article with the toolbar. Saved HTML is sanitized before storage.') }}</flux:description>
                    <div
                        x-data
                        class="overflow-hidden rounded-xl border border-slate-200 bg-white focus-within:ring-2 focus-within:ring-church-maroon-700 dark:border-zinc-700 dark:bg-zinc-950"
                    >
                        <div class="flex flex-wrap gap-1 border-b border-slate-200 p-2 dark:border-zinc-700" role="toolbar" aria-label="{{ __('Article formatting') }}">
                            <flux:button type="button" size="sm" x-on:click="document.execCommand('formatBlock', false, 'h2')" aria-label="{{ __('Heading 2') }}">H2</flux:button>
                            <flux:button type="button" size="sm" x-on:click="document.execCommand('formatBlock', false, 'h3')" aria-label="{{ __('Heading 3') }}">H3</flux:button>
                            <flux:button type="button" size="sm" x-on:click="document.execCommand('bold')" aria-label="{{ __('Bold') }}"><strong>B</strong></flux:button>
                            <flux:button type="button" size="sm" x-on:click="document.execCommand('italic')" aria-label="{{ __('Italic') }}"><em>I</em></flux:button>
                            <flux:button type="button" size="sm" icon="list-bullet" x-on:click="document.execCommand('insertUnorderedList')" aria-label="{{ __('Bulleted list') }}" />
                            <flux:button type="button" size="sm" x-on:click="document.execCommand('insertOrderedList')" aria-label="{{ __('Numbered list') }}">1.</flux:button>
                            <flux:button type="button" size="sm" x-on:click="document.execCommand('formatBlock', false, 'blockquote')" aria-label="{{ __('Blockquote') }}">❝</flux:button>
                            <flux:button type="button" size="sm" x-on:click="const url = prompt('{{ __('Link URL') }}'); if (url) document.execCommand('createLink', false, url)" aria-label="{{ __('Add link') }}">🔗</flux:button>
                            <flux:button type="button" size="sm" x-on:click="document.execCommand('insertHorizontalRule')" aria-label="{{ __('Horizontal rule') }}">―</flux:button>
                            <flux:button type="button" size="sm" icon="arrow-uturn-left" x-on:click="document.execCommand('undo')" aria-label="{{ __('Undo') }}" />
                        </div>
                        <div
                            x-ref="editor"
                            contenteditable="true"
                            role="textbox"
                            aria-multiline="true"
                            aria-label="{{ __('Article content') }}"
                            class="prose min-h-96 max-w-none p-5 outline-none dark:prose-invert"
                            x-on:input.debounce.500ms="$wire.set('form.content', $refs.editor.innerHTML)"
                        >{!! app(\App\Blog\HtmlSanitizer::class)->sanitize($form->content) !!}</div>
                    </div>
                    <details class="rounded-lg border border-slate-200 p-3 dark:border-zinc-700">
                        <summary class="cursor-pointer text-sm font-medium">{{ __('Edit HTML source') }}</summary>
                        <flux:textarea wire:model.live.debounce.600ms="form.content" rows="10" class="mt-3 font-mono text-sm" />
                    </details>
                    <flux:error name="form.content" />
                </flux:field>
                @if ($form->content)
                    <div class="rounded-xl border border-dashed border-slate-300 p-5 dark:border-zinc-700">
                        <p class="mb-4 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Sanitized preview') }}</p>
                        <div class="prose max-w-none dark:prose-invert">{!! app(\App\Blog\HtmlSanitizer::class)->sanitize($form->content) !!}</div>
                    </div>
                @endif
                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:select wire:model="form.categoryId" :label="__('Category')">
                        <flux:select.option value="">{{ __('Uncategorized') }}</flux:select.option>
                        @foreach ($categories as $category)<flux:select.option :value="$category->id" wire:key="post-category-{{ $category->id }}">{{ $category->name }}</flux:select.option>@endforeach
                    </flux:select>
                    @if ($canManageAuthors)
                        <flux:select wire:model="form.authorId" :label="__('Author')" required>
                            @foreach ($authors as $author)<flux:select.option :value="$author->id" wire:key="post-author-{{ $author->id }}">{{ $author->name }}</flux:select.option>@endforeach
                        </flux:select>
                    @endif
                </div>
                <flux:field>
                    <flux:label>{{ __('Tags') }}</flux:label>
                    <div class="grid max-h-56 gap-2 overflow-y-auto rounded-lg border border-slate-200 p-3 sm:grid-cols-2 dark:border-zinc-700">
                        @foreach ($tags as $tag)<flux:checkbox wire:model="form.tagIds" :value="$tag->id" :label="$tag->name" wire:key="post-tag-{{ $tag->id }}" />@endforeach
                    </div>
                </flux:field>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Featured image') }}</flux:heading>
            <div class="mt-5 space-y-4">
                <flux:input wire:model="form.featuredImage" type="file" :label="__('Upload image')" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" />
                <flux:input wire:model="form.featuredImageAltText" :label="__('Alternative text')" description="{{ __('Describe the image for visitors using assistive technology.') }}" />
                @if (($form->featuredImage && $form->featuredImage->isPreviewable()) || $currentImageUrl)
                    <img src="{{ $form->featuredImage && $form->featuredImage->isPreviewable() ? $form->featuredImage->temporaryUrl() : $currentImageUrl }}" alt="" class="h-56 w-full rounded-xl object-cover">
                    @if ($currentImageUrl)<flux:checkbox wire:model="form.removeFeaturedImage" :label="__('Remove current image')" />@endif
                @endif
            </div>
        </section>
    </div>

    <div class="space-y-6">
        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Publishing') }}</flux:heading>
            <div class="mt-5 space-y-5">
                <flux:select wire:model.live="form.status" :label="__('Status')">
                    @foreach (\App\PostStatus::cases() as $status)<flux:select.option :value="$status->value">{{ $status->label() }}</flux:select.option>@endforeach
                </flux:select>
                <flux:select wire:model="form.visibility" :label="__('Visibility')">
                    @foreach (\App\PostVisibility::cases() as $visibility)<flux:select.option :value="$visibility->value">{{ $visibility->label() }}</flux:select.option>@endforeach
                </flux:select>
                @if ($form->status === \App\PostStatus::Published->value)
                    <flux:input wire:model="form.publishedAt" type="datetime-local" :label="__('Published at')" required />
                @elseif ($form->status === \App\PostStatus::Scheduled->value)
                    <flux:input wire:model="form.scheduledFor" type="datetime-local" :label="__('Schedule for')" required />
                @endif
                <flux:switch wire:model="form.isFeatured" :label="__('Featured post')" />
                <flux:switch wire:model="form.allowComments" :label="__('Allow comments when available')" />
            </div>
        </section>

        <section class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('SEO') }}</flux:heading>
            <div class="mt-5 space-y-5">
                <flux:input wire:model="form.metaTitle" :label="__('Meta title')" maxlength="70" />
                <flux:textarea wire:model="form.metaDescription" :label="__('Meta description')" rows="3" maxlength="170" />
                <flux:input wire:model="form.canonicalUrl" :label="__('Canonical URL')" type="url" />
                <div class="rounded-lg border border-slate-200 p-4 dark:border-zinc-700">
                    <p class="text-sm font-semibold text-blue-700 dark:text-blue-400">{{ $form->metaTitle ?: $form->title ?: __('Post title') }}</p>
                    <p class="mt-1 text-xs text-emerald-700">{{ $form->canonicalUrl ?: url('/blog/'.($form->slug ?: 'post')) }}</p>
                    <p class="mt-1 text-sm text-slate-600 dark:text-zinc-300">{{ $form->metaDescription ?: \Illuminate\Support\Str::limit(strip_tags($form->excerpt ?: $form->content), 160) }}</p>
                </div>
            </div>
        </section>
    </div>
</div>
