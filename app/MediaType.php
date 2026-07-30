<?php

namespace App;

enum MediaType: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';
    case Spreadsheet = 'spreadsheet';
    case Presentation = 'presentation';
    case Archive = 'archive';
    case Other = 'other';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }

    public function icon(): string
    {
        return match ($this) {
            self::Image => 'photo',
            self::Video => 'video-camera',
            self::Audio => 'musical-note',
            self::Archive => 'archive-box',
            self::Spreadsheet => 'table-cells',
            self::Presentation => 'presentation-chart-bar',
            default => 'document',
        };
    }
}
