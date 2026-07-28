<?php

namespace App;

enum SermonMediaType: string
{
    case Video = 'video';
    case Audio = 'audio';
    case LivestreamReplay = 'livestream_replay';
    case PodcastEpisode = 'podcast_episode';
    case ExternalPage = 'external_page';

    public function label(): string
    {
        return match ($this) {
            self::Video => 'Video',
            self::Audio => 'Audio',
            self::LivestreamReplay => 'Livestream replay',
            self::PodcastEpisode => 'Podcast episode',
            self::ExternalPage => 'External sermon page',
        };
    }
}
