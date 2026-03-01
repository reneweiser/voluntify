<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <flux:button variant="ghost" icon="arrow-left" :href="route('events.index')" wire:navigate />
            <flux:heading size="xl">{{ $event->name }}</flux:heading>
            <flux:badge size="sm" :color="match($event->status) {
                \App\Enums\EventStatus::Published => 'green',
                \App\Enums\EventStatus::Draft => 'yellow',
                \App\Enums\EventStatus::Archived => 'zinc',
            }">
                {{ __(ucfirst($event->status->value)) }}
            </flux:badge>
        </div>

        @if ($this->canManage)
            <div class="flex items-center gap-2">
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

    <x-events.tab-nav :event="$event" />

    {{-- Share link for published events --}}
    @if ($this->publicUrl)
        <div class="mb-6 rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 p-4">
            <flux:text size="sm" class="font-medium text-green-800 dark:text-green-200">
                {{ __('Public signup link:') }}
            </flux:text>
            <flux:text size="sm" class="mt-1 font-mono text-green-700 dark:text-green-300 break-all">
                {{ $this->publicUrl }}
            </flux:text>
        </div>
    @endif

    {{-- Metric cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
            <flux:text size="sm">{{ __('Volunteers') }}</flux:text>
            <flux:heading size="xl" class="mt-1">{{ $this->volunteerCount }}</flux:heading>
        </div>
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
            <flux:text size="sm">{{ __('Jobs') }}</flux:text>
            <flux:heading size="xl" class="mt-1">{{ $this->jobCount }}</flux:heading>
        </div>
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
            <flux:text size="sm">{{ __('Shifts') }}</flux:text>
            <flux:heading size="xl" class="mt-1">{{ $this->shiftCount }}</flux:heading>
        </div>
    </div>

    {{-- Title image --}}
    @if ($event->titleImageUrl())
        <div class="mb-6">
            <img src="{{ $event->titleImageUrl() }}" alt="{{ $event->name }}" class="w-full max-h-64 object-cover rounded-lg" />
        </div>
    @endif

    {{-- Event details / edit form --}}
    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
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
                    <flux:label>{{ __('Title Image') }}</flux:label>
                    @if ($event->titleImageUrl() && !$titleImage)
                        <div class="flex items-center gap-3 mb-2">
                            <img src="{{ $event->titleImageUrl() }}" alt="{{ $event->name }}" class="h-20 w-32 object-cover rounded" />
                            <flux:button variant="danger" size="sm" icon="trash" wire:click="deleteImage" wire:confirm="{{ __('Remove this image?') }}">
                                {{ __('Remove') }}
                            </flux:button>
                        </div>
                    @endif
                    <input type="file" wire:model="titleImage" accept="image/jpeg,image/png,image/webp"
                        class="block w-full text-sm text-zinc-500 file:mr-4 file:rounded-md file:border-0 file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-medium hover:file:bg-zinc-200 dark:text-zinc-400 dark:file:bg-zinc-700 dark:hover:file:bg-zinc-600" />
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
    </div>
</div>
