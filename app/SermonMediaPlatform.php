<?php

namespace App;

enum SermonMediaPlatform: string
{
    case YouTube = 'youtube';
    case Facebook = 'facebook';
    case Vimeo = 'vimeo';
    case SoundCloud = 'soundcloud';
    case Spotify = 'spotify';
    case ApplePodcasts = 'apple_podcasts';
    case Mixcloud = 'mixcloud';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::YouTube => 'YouTube',
            self::Facebook => 'Facebook',
            self::Vimeo => 'Vimeo',
            self::SoundCloud => 'SoundCloud',
            self::Spotify => 'Spotify',
            self::ApplePodcasts => 'Apple Podcasts',
            self::Mixcloud => 'Mixcloud',
            self::Other => 'Other',
        };
    }
}
