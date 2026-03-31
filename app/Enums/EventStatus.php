<?php

namespace App\Enums;

enum EventStatus: string
{
    case Draft = 'draft';
    case PublishedOpen = 'published_open';
    case PublishedClosed = 'published_closed';
    case Archived = 'archived';

    public function isPublished(): bool
    {
        return $this === self::PublishedOpen || $this === self::PublishedClosed;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PublishedOpen => 'Published (Open)',
            self::PublishedClosed => 'Published (Closed)',
            self::Archived => 'Archived',
        };
    }
}
