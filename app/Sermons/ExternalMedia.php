<?php

namespace App\Sermons;

use App\SermonMediaPlatform;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ExternalMedia
{
    /**
     * @return array{
     *     original_url: string,
     *     platform: SermonMediaPlatform,
     *     embed_url: string|null,
     *     thumbnail_url: string|null
     * }
     */
    public function inspect(string $url): array
    {
        $url = trim($url);

        if ($url === '' || Str::contains($url, ['<', '>', '"', "'", "\r", "\n"])) {
            throw new InvalidArgumentException('Enter a valid HTTPS media URL, not embed code or HTML.');
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw new InvalidArgumentException('The external media URL must be a valid HTTPS URL.');
        }

        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
        $host = Str::startsWith($host, 'www.') ? Str::after($host, 'www.') : $host;

        return match (true) {
            $this->hostMatches($host, ['youtube.com', 'youtu.be', 'youtube-nocookie.com']) => $this->youtube($url, $host),
            $this->hostMatches($host, ['vimeo.com']) => $this->vimeo($url),
            $this->hostMatches($host, ['facebook.com', 'fb.watch']) => $this->facebook($url),
            $this->hostMatches($host, ['soundcloud.com']) => $this->soundCloud($url),
            $this->hostMatches($host, ['open.spotify.com']) => $this->spotify($url),
            $this->hostMatches($host, ['podcasts.apple.com']) => $this->applePodcasts($url),
            $this->hostMatches($host, ['mixcloud.com']) => $this->mixcloud($url),
            default => $this->external($url),
        };
    }

    public function isEmbeddableUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return false;
        }

        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

        return $this->hostMatches($host, [
            'youtube-nocookie.com',
            'player.vimeo.com',
            'facebook.com',
            'w.soundcloud.com',
            'open.spotify.com',
            'embed.podcasts.apple.com',
            'mixcloud.com',
        ]);
    }

    public function safeAuditUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH);

        return $scheme !== null && $host !== null ? "{$scheme}://{$host}{$path}" : null;
    }

    /**
     * @return array{original_url: string, platform: SermonMediaPlatform, embed_url: string, thumbnail_url: string}
     */
    private function youtube(string $url, string $host): array
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $videoId = $host === 'youtu.be'
            ? Str::before($path, '/')
            : ($query['v'] ?? $this->youtubePathIdentifier($path));

        if (! is_string($videoId) || preg_match('/^[A-Za-z0-9_-]{6,20}$/', $videoId) !== 1) {
            throw new InvalidArgumentException('The YouTube URL does not contain a valid video identifier.');
        }

        return [
            'original_url' => $url,
            'platform' => SermonMediaPlatform::YouTube,
            'embed_url' => "https://www.youtube-nocookie.com/embed/{$videoId}",
            'thumbnail_url' => "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg",
        ];
    }

    private function youtubePathIdentifier(string $path): string
    {
        $segments = explode('/', $path);

        return in_array($segments[0], ['embed', 'shorts', 'live'], true)
            ? ($segments[1] ?? '')
            : '';
    }

    /**
     * @return array{original_url: string, platform: SermonMediaPlatform, embed_url: string, thumbnail_url: null}
     */
    private function vimeo(string $url): array
    {
        $segments = array_values(array_filter(explode('/', trim((string) parse_url($url, PHP_URL_PATH), '/'))));
        $videoId = collect($segments)->first(fn (string $segment): bool => ctype_digit($segment));

        if ($videoId === null) {
            throw new InvalidArgumentException('The Vimeo URL does not contain a valid video identifier.');
        }

        return [
            'original_url' => $url,
            'platform' => SermonMediaPlatform::Vimeo,
            'embed_url' => "https://player.vimeo.com/video/{$videoId}",
            'thumbnail_url' => null,
        ];
    }

    /**
     * @return array{original_url: string, platform: SermonMediaPlatform, embed_url: string, thumbnail_url: null}
     */
    private function facebook(string $url): array
    {
        return [
            'original_url' => $url,
            'platform' => SermonMediaPlatform::Facebook,
            'embed_url' => 'https://www.facebook.com/plugins/video.php?href='.rawurlencode($url).'&show_text=false',
            'thumbnail_url' => null,
        ];
    }

    /**
     * @return array{original_url: string, platform: SermonMediaPlatform, embed_url: string, thumbnail_url: null}
     */
    private function soundCloud(string $url): array
    {
        return [
            'original_url' => $url,
            'platform' => SermonMediaPlatform::SoundCloud,
            'embed_url' => 'https://w.soundcloud.com/player/?url='.rawurlencode($url),
            'thumbnail_url' => null,
        ];
    }

    /**
     * @return array{original_url: string, platform: SermonMediaPlatform, embed_url: string, thumbnail_url: null}
     */
    private function spotify(string $url): array
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $segments = explode('/', $path);

        if (! in_array($segments[0], ['episode', 'show', 'track'], true)
            || preg_match('/^[A-Za-z0-9]+$/', $segments[1] ?? '') !== 1
        ) {
            throw new InvalidArgumentException('The Spotify URL must point to an episode, show, or track.');
        }

        return [
            'original_url' => $url,
            'platform' => SermonMediaPlatform::Spotify,
            'embed_url' => 'https://open.spotify.com/embed/'.$segments[0].'/'.$segments[1],
            'thumbnail_url' => null,
        ];
    }

    /**
     * @return array{original_url: string, platform: SermonMediaPlatform, embed_url: string, thumbnail_url: null}
     */
    private function applePodcasts(string $url): array
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $embedUrl = 'https://embed.podcasts.apple.com'.$path;

        $episodeId = $query['i'] ?? null;

        if (is_string($episodeId) && ctype_digit($episodeId)) {
            $embedUrl .= '?i='.$episodeId;
        }

        return [
            'original_url' => $url,
            'platform' => SermonMediaPlatform::ApplePodcasts,
            'embed_url' => $embedUrl,
            'thumbnail_url' => null,
        ];
    }

    /**
     * @return array{original_url: string, platform: SermonMediaPlatform, embed_url: string, thumbnail_url: null}
     */
    private function mixcloud(string $url): array
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        if (preg_match('#^/[^/]+/[^/]+/?$#', $path) !== 1) {
            throw new InvalidArgumentException('The Mixcloud URL must point to a published show.');
        }

        return [
            'original_url' => $url,
            'platform' => SermonMediaPlatform::Mixcloud,
            'embed_url' => 'https://www.mixcloud.com/widget/iframe/?hide_cover=1&feed='.rawurlencode($path),
            'thumbnail_url' => null,
        ];
    }

    /**
     * @return array{original_url: string, platform: SermonMediaPlatform, embed_url: null, thumbnail_url: null}
     */
    private function external(string $url): array
    {
        return [
            'original_url' => $url,
            'platform' => SermonMediaPlatform::Other,
            'embed_url' => null,
            'thumbnail_url' => null,
        ];
    }

    /**
     * @param  list<string>  $allowedHosts
     */
    private function hostMatches(string $host, array $allowedHosts): bool
    {
        return collect($allowedHosts)->contains(
            fn (string $allowedHost): bool => $host === $allowedHost || Str::endsWith($host, '.'.$allowedHost),
        );
    }
}
