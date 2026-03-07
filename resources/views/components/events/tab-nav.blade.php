@props(['event'])

<flux:navbar class="mb-8 overflow-x-auto">
    <flux:navbar.item
        :href="route('events.show', $event)"
        :current="request()->routeIs('events.show')"
        icon="information-circle"
        wire:navigate
    >
        {{ __('Overview') }}
    </flux:navbar.item>
    <flux:navbar.item
        :href="route('events.jobs', $event)"
        :current="request()->routeIs('events.jobs')"
        icon="briefcase"
        wire:navigate
    >
        {{ __('Jobs & Shifts') }}
    </flux:navbar.item>
    @can('view', $event)
        <flux:navbar.item
            :href="route('events.volunteers', $event)"
            :current="request()->routeIs('events.volunteers*')"
            icon="user-group"
            wire:navigate
        >
            {{ __('Volunteers') }}
        </flux:navbar.item>
    @endcan
    <flux:navbar.item
        :href="route('events.emails', $event)"
        :current="request()->routeIs('events.emails')"
        icon="envelope"
        wire:navigate
    >
        {{ __('Emails') }}
    </flux:navbar.item>
    @can('markAttendance', $event)
        <flux:navbar.item
            :href="route('events.attendance', $event)"
            :current="request()->routeIs('events.attendance')"
            icon="clipboard-document-check"
            wire:navigate
        >
            {{ __('Attendance') }}
        </flux:navbar.item>
    @endcan
</flux:navbar>
