<div class="mx-auto max-w-7xl p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
        <div class="flex items-center gap-3">
            <flux:button variant="ghost" icon="arrow-left" :href="route('events.index')" wire:navigate />
            <flux:heading size="xl">{{ $event->name }}</flux:heading>
            <flux:badge size="sm" :color="match($event->status) {
                \App\Enums\EventStatus::Published => 'emerald',
                \App\Enums\EventStatus::Draft => 'amber',
                \App\Enums\EventStatus::Archived => 'zinc',
            }">
                {{ __(ucfirst($event->status->value)) }}
            </flux:badge>
        </div>

        @if ($this->canManage)
            <div class="flex items-center gap-2">
                <flux:button variant="subtle" icon="document-duplicate" wire:click="cloneEvent" wire:confirm="{{ __('Clone this event? A new Draft event will be created with the same jobs and shifts.') }}">
                    {{ __('Clone') }}
                </flux:button>
                @if ($event->status === \App\Enums\EventStatus::Draft)
                    <flux:button variant="primary" wire:click="publishEvent" wire:confirm="{{ __('Publish this event? It will become publicly accessible.') }}">
                        {{ __('Publish') }}
                    </flux:button>
                @elseif ($event->status === \App\Enums\EventStatus::Published)
                    <flux:button variant="subtle" wire:click="archiveEvent" wire:confirm="{{ __('Archive this event? Volunteers will no longer be able to sign up.') }}">
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

    {{-- Group badge --}}
    @if ($event->eventGroup)
        <div class="mb-4">
            <a href="{{ route('event-groups.show', $event->eventGroup) }}" wire:navigate class="inline-flex items-center gap-1.5">
                <flux:badge size="sm" color="sky" icon="folder">
                    {{ $event->eventGroup->name }}
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

        {{-- Event details / edit form --}}
        <flux:card>
            @if ($editing)
                <form wire:submit="saveEvent" class="space-y-4">
                    <flux:field>
                        <flux:label>{{ __('Event Name') }}</flux:label>
                        <flux:input wire:model="name" />
                        <flux:error name="name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Description') }}</flux:label>
                        <flux:textarea wire:model="description" rows="3" />
                        <flux:error name="description" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Location') }}</flux:label>
                        <flux:input wire:model="location" />
                        <flux:error name="location" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Starts At') }}</flux:label>
                        <flux:input type="datetime-local" wire:model="startsAt" />
                        <flux:error name="startsAt" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Ends At') }}</flux:label>
                        <flux:input type="datetime-local" wire:model="endsAt" />
                        <flux:error name="endsAt" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Cancellation Cutoff (hours)') }}</flux:label>
                        <flux:input type="number" wire:model="cancellationCutoffHours" min="1" max="168" placeholder="{{ __('Disabled — leave empty') }}" />
                        <flux:description>{{ __('Volunteers can cancel signups up to this many hours before their shift. Leave empty to disable.') }}</flux:description>
                        <flux:error name="cancellationCutoffHours" />
                    </flux:field>

                    @if ($this->availableGroups->isNotEmpty())
                        <flux:field>
                            <flux:label>{{ __('Event Group') }}</flux:label>
                            <flux:select wire:model="selectedGroupId" wire:change="updateGroup" placeholder="{{ __('None') }}">
                                <flux:select.option value="">{{ __('None') }}</flux:select.option>
                                @foreach ($this->availableGroups as $group)
                                    <flux:select.option :value="$group->id">{{ $group->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:field>
                    @endif

                    <flux:field>
                        <flux:label>{{ __('Title Image') }}</flux:label>
                        @if ($event->titleImageUrl() && !$titleImage)
                            <div class="flex items-center gap-3 mb-2">
                                <img src="{{ $event->titleImageUrl() }}" alt="{{ $event->name }}" class="h-20 w-32 object-cover rounded" />
                                <flux:button variant="danger" size="sm" icon="trash" wire:click="deleteImage" wire:confirm="{{ __('Remove this image?') }}">
                                    {{ __('Remove') }}
                                </flux:button>
                            </div>
                        @endif
                        <flux:input type="file" wire:model="titleImage" accept="image/jpeg,image/png,image/webp" />
                        <flux:error name="titleImage" />
                    </flux:field>

                    <div class="flex gap-2">
                        <flux:button type="submit" variant="primary">{{ __('Save Changes') }}</flux:button>
                        <flux:button variant="ghost" wire:click="cancelEditing">{{ __('Cancel') }}</flux:button>
                    </div>
                </form>
            @else
                <div class="flex items-start justify-between">
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
                    </div>

                    @if ($this->canManage && $event->status !== \App\Enums\EventStatus::Archived)
                        <flux:button variant="subtle" size="sm" icon="pencil" wire:click="startEditing">
                            {{ __('Edit') }}
                        </flux:button>
                    @endif
                </div>
            @endif
        </flux:card>
    </x-events.layout>
</div>
