<?php

namespace App\Enums;

enum ActivityCategory: string
{
    case Event = 'event';
    case Job = 'job';
    case Shift = 'shift';
    case Volunteer = 'volunteer';
    case Attendance = 'attendance';
    case Member = 'member';
    case Email = 'email';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Event => 'Event',
            self::Job => 'Job',
            self::Shift => 'Shift',
            self::Volunteer => 'Volunteer',
            self::Attendance => 'Attendance',
            self::Member => 'Member',
            self::Email => 'Email',
            self::System => 'System',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Event => 'calendar',
            self::Job => 'briefcase',
            self::Shift => 'clock',
            self::Volunteer => 'user-group',
            self::Attendance => 'clipboard-document-check',
            self::Member => 'users',
            self::Email => 'envelope',
            self::System => 'cog',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Event => 'blue',
            self::Job => 'purple',
            self::Shift => 'amber',
            self::Volunteer => 'emerald',
            self::Attendance => 'teal',
            self::Member => 'indigo',
            self::Email => 'sky',
            self::System => 'zinc',
        };
    }
}
