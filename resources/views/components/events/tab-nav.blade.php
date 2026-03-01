@props(['event'])

<flux:navbar class="mb-6">
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
    <flux:navbar.item
        :href="route('events.emails', $event)"
        :current="request()->routeIs('events.emails')"
        icon="envelope"
        wire:navigate
    >
        {{ __('Emails') }}
    </flux:navbar.item>
</flux:navbar>
