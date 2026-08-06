<?php

namespace App;

enum ContactSubmissionCategory: string
{
    case General = 'general';
    case Membership = 'membership';
    case Counselling = 'counselling';
    case Partnership = 'partnership';
    case Media = 'media';
    case Event = 'event';
    case Ministry = 'ministry';
    case Giving = 'giving';
    case WebsiteSupport = 'website_support';
    case Feedback = 'feedback';
    case Complaint = 'complaint';
    case Other = 'other';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->headline()->toString();
    }
}
