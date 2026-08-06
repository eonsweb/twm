<?php

namespace App;

enum PageType: string
{
    case Standard = 'standard';
    case Homepage = 'homepage';
    case About = 'about';
    case Leadership = 'leadership';
    case Ministries = 'ministries';
    case Sermons = 'sermons';
    case Events = 'events';
    case Blog = 'blog';
    case Books = 'books';
    case Donation = 'donation';
    case PrayerRequest = 'prayer-request';
    case Contact = 'contact';
    case Legal = 'legal';
    case Custom = 'custom';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
