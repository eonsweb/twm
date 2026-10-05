@props(['social' => []])
@foreach(['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'x' => 'X'] as $key => $label)
    @if(($social[$key.'_enabled'] ?? false) && filled($social[$key.'_url'] ?? null))
        <a href="{{ $social[$key.'_url'] }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $label }}" class="grid size-11 place-items-center rounded-full border border-white/30 text-white transition hover:border-[var(--twm-accent)] hover:text-[var(--twm-accent)]">
            <svg viewBox="0 0 24 24" class="size-5" fill="currentColor" aria-hidden="true">
                @switch($key)
                    @case('facebook')<path d="M14 22v-9h3l.5-4H14V7c0-1 .3-2 2-2h2V1.5A24 24 0 0 0 15 1c-3 0-5 2-5 5v3H7v4h3v9z"/>@break
                    @case('instagram')<rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1"/>@break
                    @case('youtube')<path d="M21 5c-1-1-17-1-18 0-2 2-2 12 0 14 1 1 17 1 18 0 2-2 2-12 0-14ZM10 16V8l6 4z"/>@break
                    @case('tiktok')<path d="M14 2h3c0 3 2 5 5 5v3c-2 0-4-1-5-2v8a6 6 0 1 1-6-6v3a3 3 0 1 0 3 3z"/>@break
                    @case('x')<path d="m3 3 7 10-7 8h3l6-6 4 6h5l-8-11 7-7h-3l-5 5-4-5zm4 2h1l10 14h-1z"/>@break
                @endswitch
            </svg>
        </a>
    @endif
@endforeach
