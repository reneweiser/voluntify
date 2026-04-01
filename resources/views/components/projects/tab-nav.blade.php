@props(['project'])

<flux:navlist aria-label="{{ __('Project') }}">
    <flux:navlist.item
        :href="route('projects.show', $project)"
        :current="request()->routeIs('projects.show')"
        icon="information-circle"
        wire:navigate
    >
        {{ __('Overview') }}
    </flux:navlist.item>
    @can('manageMembers', $project)
        <flux:navlist.item
            :href="route('projects.members', $project)"
            :current="request()->routeIs('projects.members')"
            icon="users"
            wire:navigate
        >
            {{ __('Members') }}
        </flux:navlist.item>
    @endcan
    @can('manageScanners', $project)
        <flux:navlist.item
            :href="route('projects.scanners', $project)"
            :current="request()->routeIs('projects.scanners')"
            icon="qr-code"
            wire:navigate
        >
            {{ __('Scanners') }}
        </flux:navlist.item>
    @endcan
    @can('view', $project)
        <flux:navlist.item
            :href="route('projects.gear-summary', $project)"
            :current="request()->routeIs('projects.gear-summary')"
            icon="cube"
            wire:navigate
        >
            {{ __('Gear') }}
        </flux:navlist.item>
    @endcan
    @can('update', $project)
        <flux:navlist.item
            :href="route('projects.announcements', $project)"
            :current="request()->routeIs('projects.announcements')"
            icon="megaphone"
            wire:navigate
        >
            {{ __('Ankündigungen') }}
        </flux:navlist.item>
        <flux:navlist.item
            :href="route('projects.website-editor', $project)"
            :current="request()->routeIs('projects.website-editor')"
            icon="globe-alt"
            wire:navigate
        >
            {{ __('Website') }}
        </flux:navlist.item>
        <flux:navlist.item
            :href="route('projects.hint-texts', $project)"
            :current="request()->routeIs('projects.hint-texts')"
            icon="information-circle"
            wire:navigate
        >
            {{ __('Hinweistexte') }}
        </flux:navlist.item>
    @endcan
</flux:navlist>
