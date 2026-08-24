<?php

use App\Models\Person;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.public')] class extends Component
{
    use WithPagination;

    public Person $speaker;

    public function mount(Person $speaker): void
    {
        abort_unless($speaker->is_active && $speaker->is_public, 404);
        $this->speaker = $speaker->load('primaryLeadershipAssignment.position');
    }

    public function title(): string
    {
        return $this->speaker->full_name;
    }

    #[Computed]
    public function sermons(): LengthAwarePaginator
    {
        return $this->speaker->sermons()
            ->publiclyAvailable()
            ->with('speaker:id,title,first_name,middle_name,last_name')
            ->orderByDesc('sermon_date')
            ->paginate(12);
    }
};
?>

<div>
    <header class="bg-church-maroon-950 text-white"><div class="mx-auto flex max-w-7xl flex-col gap-7 px-4 pb-16 pt-28 sm:flex-row sm:items-center sm:px-6 sm:pt-32 lg:px-8 lg:pt-36">@if ($speaker->photo_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($speaker->photo_path) }}" alt="{{ $speaker->full_name }}" class="size-36 rounded-full border-4 border-church-gold-400 object-cover">@else<div class="flex size-36 items-center justify-center rounded-full bg-white/10"><flux:icon.user class="size-16" /></div>@endif<div><a href="{{ route('public.sermons.index') }}" class="text-sm font-semibold text-church-gold-400" wire:navigate>&larr; {{ __('All sermons') }}</a><h1 class="mt-4 text-4xl font-bold">{{ $speaker->full_name }}</h1><p class="mt-2 text-lg text-church-gold-400">{{ $speaker->primaryLeadershipAssignment?->display_title ?? $speaker->primaryLeadershipAssignment?->position?->name ?? __('Guest speaker') }}</p>@if ($speaker->short_bio)<p class="mt-4 max-w-3xl leading-7 text-white/75">{{ $speaker->short_bio }}</p>@endif</div></div></header>
    <div class="mx-auto max-w-7xl space-y-10 bg-white px-4 py-10 text-zinc-950 sm:px-6 lg:px-8">@if ($speaker->biography)<section class="max-w-3xl"><h2 class="text-2xl font-bold">{{ __('About') }}</h2><div class="mt-4 whitespace-pre-line leading-8 text-slate-700 dark:text-zinc-300">{{ $speaker->biography }}</div></section>@endif<section><h2 class="mb-5 text-2xl font-bold">{{ __('Sermons by :speaker', ['speaker' => $speaker->full_name]) }}</h2>@if ($this->sermons->isEmpty())<p>{{ __('No published sermons are available for this speaker.') }}</p>@else<div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">@foreach ($this->sermons as $sermon)<x-sermons.card :sermon="$sermon" wire:key="speaker-sermon-{{ $sermon->id }}" />@endforeach</div><div class="mt-8"><flux:pagination :paginator="$this->sermons" /></div>@endif</section></div>
</div>
