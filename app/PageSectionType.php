<?php

namespace App;

enum PageSectionType: string
{
    case Hero = 'hero';
    case RichText = 'rich-text';
    case ImageText = 'image-text';
    case CallToAction = 'call-to-action';
    case FeaturedSermons = 'featured-sermons';
    case UpcomingEvents = 'upcoming-events';
    case LatestPosts = 'latest-posts';
    case MinistriesGrid = 'ministries-grid';
    case LeadershipGrid = 'leadership-grid';
    case ServiceTimes = 'service-times';
    case Welcome = 'welcome';
    case NextSteps = 'next-steps';
    case PrayerGiving = 'prayer-giving';
    case WelcomeUpcomingEvent = 'welcome-upcoming-event';
    case ChurchLocations = 'church-locations';
    case Testimonials = 'testimonials';
    case FeaturedBook = 'featured-book';
    case BooksGrid = 'books-grid';
    case DonationCallout = 'donation-callout';
    case PrayerRequestCallout = 'prayer-request-callout';
    case Livestream = 'livestream';
    case Gallery = 'gallery';
    case Statistics = 'statistics';
    case ContactDetails = 'contact-details';
    case Newsletter = 'newsletter';
    case Custom = 'custom';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
