<?php

namespace App;

enum ContactSubmissionStatus: string
{
    case New = 'new';
    case Read = 'read';
    case InProgress = 'in_progress';
    case WaitingForVisitor = 'waiting_for_visitor';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Spam = 'spam';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->headline()->toString();
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'blue', self::Read => 'zinc', self::InProgress => 'amber',
            self::WaitingForVisitor => 'purple', self::Resolved => 'green',
            self::Closed => 'zinc', self::Spam => 'red',
        };
    }
}
