<div class="mx-auto max-w-7xl p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
        <div class="flex items-center gap-3">
            <flux:button variant="ghost" icon="arrow-left" :href="route('events.index')" wire:navigate aria-label="{{ __('Back to events') }}" />
            <flux:heading size="xl">{{ $event->name }}</flux:heading>
            <flux:badge size="sm" :color="match($event->status) {
                \App\Enums\EventStatus::PublishedOpen => 'emerald',
                \App\Enums\EventStatus::PublishedClosed => 'yellow',
                \App\Enums\EventStatus::Draft => 'amber',
                \App\Enums\EventStatus::Archived => 'zinc',
            }">
                {{ $event->status->label() }}
            </flux:badge>
            @if ($event->visibility === \App\Enums\EventVisibility::Private)
                <flux:badge size="sm" color="purple">{{ __('Private') }}</flux:badge>
            @endif
        </div>

        @if ($this->canManage)
            <div class="flex items-center gap-2">
                <flux:button variant="subtle" icon="cog-6-tooth" :href="route('events.settings', $event)" wire:navigate>
                    {{ __('Settings') }}
                </flux:button>
                <flux:button variant="subtle" icon="document-duplicate" wire:click="cloneEvent" wire:confirm="{{ __('Clone this event? A new Draft event will be created with the same jobs and shifts.') }}">
                    {{ __('Clone') }}
                </flux:button>
                @if ($event->status === \App\Enums\EventStatus::Draft)
                    <flux:button variant="primary" wire:click="publishEvent" wire:confirm="{{ __('Publish this event? It will become publicly accessible.') }}">
                        {{ __('Publish') }}
                    </flux:button>
                @elseif ($event->status === \App\Enums\EventStatus::PublishedOpen)
                    <flux:button variant="subtle" wire:click="closeRegistration" wire:confirm="{{ __('Close registration? Volunteers will no longer be able to sign up, but the event page remains visible.') }}">
                        {{ __('Close Registration') }}
                    </flux:button>
                    <flux:button variant="subtle" wire:click="archiveEvent" wire:confirm="{{ __('Archive this event? It will be removed from public view.') }}">
                        {{ __('Archive') }}
                    </flux:button>
                @elseif ($event->status === \App\Enums\EventStatus::PublishedClosed)
                    <flux:button variant="subtle" wire:click="archiveEvent" wire:confirm="{{ __('Archive this event? It will be removed from public view.') }}">
                        {{ __('Archive') }}
                    </flux:button>
                @endif
            </div>
        @endif
    </div>

    {{-- Status errors --}}
    @error('status')
        <flux:callout variant="danger" class="mb-4">{{ $message }}</flux:callout>
    @enderror

    {{-- Project badge --}}
    @if ($event->project)
        <div class="mb-4">
            <a href="{{ route('projects.show', $event->project) }}" wire:navigate class="inline-flex items-center gap-1.5">
                <flux:badge size="sm" color="sky" icon="folder">
                    {{ $event->project->name }}
                </flux:badge>
            </a>
        </div>
    @endif

    <x-events.layout :event="$event">
        {{-- Share link for published events --}}
        @if ($this->publicUrl)
            <div class="mb-6 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/20 p-4 flex items-start gap-3">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400">
                    <flux:icon name="link" class="size-4" />
                </div>
                <div>
                    <flux:text size="sm" class="font-medium text-emerald-800 dark:text-emerald-200">
                        {{ __('Public signup link:') }}
                    </flux:text>
                    <flux:text size="sm" class="mt-1 font-mono text-emerald-700 dark:text-emerald-300 break-all">
                        {{ $this->publicUrl }}
                    </flux:text>
                </div>
            </div>
        @endif

        {{-- Metric cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 card-accent-emerald card-gradient-emerald">
                <div class="flex items-center gap-3">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400">
                        <flux:icon name="user-group" variant="mini" class="size-5" />
                    </div>
                    <div>
                        <flux:text size="sm">{{ __('Volunteers') }}</flux:text>
                        <flux:heading size="xl">{{ $this->volunteerCount }}</flux:heading>
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 card-accent-amber card-gradient-amber">
                <div class="flex items-center gap-3">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400">
                        <flux:icon name="briefcase" variant="mini" class="size-5" />
                    </div>
                    <div>
                        <flux:text size="sm">{{ __('Jobs') }}</flux:text>
                        <flux:heading size="xl">{{ $this->jobCount }}</flux:heading>
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 card-accent-sky card-gradient-sky">
                <div class="flex items-center gap-3">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-sky-100 dark:bg-sky-900/40 text-sky-600 dark:text-sky-400">
                        <flux:icon name="clock" variant="mini" class="size-5" />
                    </div>
                    <div>
                        <flux:text size="sm">{{ __('Shifts') }}</flux:text>
                        <flux:heading size="xl">{{ $this->shiftCount }}</flux:heading>
                    </div>
                </div>
            </div>
        </div>

        {{-- Title image --}}
        @if ($event->titleImageUrl())
            <div class="mb-6">
                <img src="{{ $event->titleImageUrl() }}" alt="{{ $event->name }}" class="w-full max-h-64 object-cover rounded-xl shadow-sm" />
            </div>
        @endif

        {{-- Event details (read-only) --}}
        <flux:card>
            <div class="space-y-3">
                @if ($event->description)
                    <div>
                        <flux:text size="sm" class="font-medium">{{ __('Description') }}</flux:text>
                        <flux:text class="mt-1">{{ $event->description }}</flux:text>
                    </div>
                @endif

                @if ($event->location)
                    <div>
                        <flux:text size="sm" class="font-medium">{{ __('Location') }}</flux:text>
                        <flux:text class="mt-1">{{ $event->location }}</flux:text>
                    </div>
                @endif

                <div>
                    <flux:text size="sm" class="font-medium">{{ __('Date & Time') }}</flux:text>
                    <flux:text class="mt-1">
                        {{ $event->starts_at->format('M d, Y g:i A') }} &mdash; {{ $event->ends_at->format('M d, Y g:i A') }}
                    </flux:text>
                </div>

                @if ($event->visibility === \App\Enums\EventVisibility::Private)
                    <div>
                        <flux:text size="sm" class="font-medium">{{ __('Visibility') }}</flux:text>
                        <flux:text class="mt-1">{{ __('Private') }}</flux:text>
                    </div>
                @endif

                @if ($event->notification_email)
                    <div>
                        <flux:text size="sm" class="font-medium">{{ __('Benachrichtigungs-E-Mail') }}</flux:text>
                        <flux:text class="mt-1">{{ $event->notification_email }}</flux:text>
                    </div>
                @endif

                @if ($event->attendance_grace_minutes)
                    <div>
                        <flux:text size="sm" class="font-medium">{{ __('Grace Period') }}</flux:text>
                        <flux:text class="mt-1">{{ $event->attendance_grace_minutes }} {{ __('minutes') }}</flux:text>
                    </div>
                @endif
            </div>
        </flux:card>
    </x-events.layout>
</div>
