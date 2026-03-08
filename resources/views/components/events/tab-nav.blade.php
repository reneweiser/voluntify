@props(['event'])

<flux:navlist aria-label="{{ __('Event') }}">
    <flux:navlist.item
        :href="route('events.show', $event)"
        :current="request()->routeIs('events.show')"
        icon="information-circle"
        wire:navigate
    >
        {{ __('Overview') }}
    </flux:navlist.item>
    <flux:navlist.item
        :href="route('events.jobs', $event)"
        :current="request()->routeIs('events.jobs')"
        icon="briefcase"
        wire:navigate
    >
        {{ __('Jobs & Shifts') }}
    </flux:navlist.item>
    @can('view', $event)
        <flux:navlist.item
            :href="route('events.volunteers', $event)"
            :current="request()->routeIs('events.volunteers*')"
            icon="user-group"
            wire:navigate
        >
            {{ __('Volunteers') }}
        </flux:navlist.item>
    @endcan
    <flux:navlist.item
        :href="route('events.emails', $event)"
        :current="request()->routeIs('events.emails')"
        icon="envelope"
        wire:navigate
    >
        {{ __('Emails') }}
    </flux:navlist.item>
    @can('update', $event)
        <flux:navlist.item
            :href="route('events.announcements', $event)"
            :current="request()->routeIs('events.announcements')"
            icon="megaphone"
            wire:navigate
        >
            {{ __('Announcements') }}
        </flux:navlist.item>
    @endcan
    @can('manageGear', $event)
        <flux:navlist.item
            :href="route('events.gear', $event)"
            :current="request()->routeIs('events.gear')"
            icon="gift"
            wire:navigate
        >
            {{ __('Gear') }}
        </flux:navlist.item>
    @endcan
    @can('trackGearPickup', $event)
        <flux:navlist.item
            :href="route('events.gear-tracker', $event)"
            :current="request()->routeIs('events.gear-tracker')"
            icon="hand-raised"
            wire:navigate
        >
            {{ __('Gear Pickup') }}
        </flux:navlist.item>
    @endcan
    @can('markAttendance', $event)
        <flux:navlist.item
            :href="route('events.attendance', $event)"
            :current="request()->routeIs('events.attendance')"
            icon="clipboard-document-check"
            wire:navigate
        >
            {{ __('Attendance') }}
        </flux:navlist.item>
    @endcan
</flux:navlist>
