<?php

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.public')] class extends Component
{
    use WithPagination;

    public string $type;
    public string $value;
    public string $heading;

    public function mount(string $type, string $value): void
    {
        abort_unless(in_array($type, ['category', 'tag', 'author', 'search'], true), 404);
        $this->type = $type; $this->value = $value;
        $this->heading = match ($type) {
            'category' => PostCategory::where('slug', $value)->value('name'),
            'tag' => Tag::where('slug', $value)->value('name'),
            'author' => User::whereKey($value)->value('name'),
            default => __('Search: :term', ['term' => $value]),
        } ?? abort(404);
    }

    public function title(): string { return $this->heading; }

    #[Computed]
    public function posts(): LengthAwarePaginator
    {
        return Post::query()->publiclyVisible()->with(['author:id,name', 'category:id,name,slug'])
            ->when($this->type === 'category', fn (Builder $query): Builder => $query->whereHas('category', fn (Builder $category): Builder => $category->where('slug', $this->value)))
            ->when($this->type === 'tag', fn (Builder $query): Builder => $query->whereHas('tags', fn (Builder $tag): Builder => $tag->where('slug', $this->value)))
            ->when($this->type === 'author', fn (Builder $query): Builder => $query->where('author_id', $this->value))
            ->when($this->type === 'search', fn (Builder $query): Builder => $query->search($this->value))
            ->orderByRaw('COALESCE(published_at, scheduled_for) DESC')->paginate(12);
    }
};
?>

<div><header class="bg-church-maroon-950 py-14 text-white"><div class="mx-auto max-w-7xl px-4 sm:px-6"><p class="text-sm font-bold uppercase tracking-widest text-church-gold-400">{{ __(str($type)->headline()->toString()) }}</p><h1 class="mt-2 text-4xl font-black">{{ $heading }}</h1></div></header><section class="mx-auto max-w-7xl px-4 py-12 sm:px-6">@if($this->posts->isEmpty())<p class="rounded-xl border border-dashed p-10 text-center text-slate-500">{{ __('No published articles found.') }}</p>@else<div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">@foreach($this->posts as $post)<a href="{{ route('blog.show', $post) }}" class="rounded-xl border border-stone-200 p-6 hover:shadow-lg dark:border-zinc-800" wire:navigate><span class="text-xs font-bold uppercase text-church-maroon-700 dark:text-church-gold-400">{{ $post->category?->name ?? __('Article') }}</span><h2 class="mt-2 text-xl font-bold">{{ $post->title }}</h2><p class="mt-3 line-clamp-3 text-sm text-slate-500">{{ $post->excerpt }}</p><p class="mt-5 text-xs text-slate-500">{{ $post->author->name }} · {{ $post->publicDate()?->format('M j, Y') }}</p></a>@endforeach</div><div class="mt-8"><flux:pagination :paginator="$this->posts" /></div>@endif</section></div>
