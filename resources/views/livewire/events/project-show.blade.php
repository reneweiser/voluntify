<div class="mx-auto max-w-7xl p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
        <div class="flex items-center gap-3">
            <flux:button variant="ghost" icon="arrow-left" :href="route('projects.index')" wire:navigate aria-label="{{ __('Back to projects') }}" />
            <flux:heading size="xl">{{ $project->name }}</flux:heading>
        </div>

        <div class="flex items-center gap-2">
            @if ($this->canManageMembers)
                <flux:button variant="subtle" size="sm" icon="users" :href="route('projects.members', $project)" wire:navigate>
                    {{ __('Members') }}
                </flux:button>
            @endif

            @if ($this->canManage)
                <flux:button variant="danger" size="sm" icon="trash" wire:click="deleteProject" wire:confirm="{{ __('Delete this project? Events will remain but be unlinked from this project.') }}">
                    {{ __('Delete Project') }}
                </flux:button>
            @endif
        </div>
    </div>

    {{-- Public link --}}
    <div class="mb-6 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/20 p-4 flex items-start gap-3">
        <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400">
            <flux:icon name="link" class="size-4" />
        </div>
        <div>
            <flux:text size="sm" class="font-medium text-emerald-800 dark:text-emerald-200">
                {{ __('Public project page:') }}
            </flux:text>
            <flux:text size="sm" class="mt-1 font-mono text-emerald-700 dark:text-emerald-300 break-all">
                {{ $this->publicUrl }}
            </flux:text>
        </div>
    </div>

    {{-- Title image --}}
    @if ($project->titleImageUrl() && !$editing)
        <div class="mb-6">
            <img src="{{ $project->titleImageUrl() }}" alt="{{ $project->name }}" class="w-full max-h-64 object-cover rounded-xl shadow-sm" />
        </div>
    @endif

    {{-- Project details / edit form --}}
    <flux:card class="mb-6">
        @if ($editing)
            <form wire:submit="saveProject" class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Project Name') }}</flux:label>
                    <flux:input wire:model="name" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Description') }}</flux:label>
                    <flux:textarea wire:model="description" rows="3" />
                    <flux:error name="description" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Title Image') }}</flux:label>
                    @if ($project->titleImageUrl() && !$titleImage)
                        <div class="flex items-center gap-3 mb-2">
                            <img src="{{ $project->titleImageUrl() }}" alt="{{ $project->name }}" class="h-20 w-32 object-cover rounded" />
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
                    @if ($project->description)
                        <div>
                            <flux:text size="sm" class="font-medium">{{ __('Description') }}</flux:text>
                            <flux:text class="mt-1">{{ $project->description }}</flux:text>
                        </div>
                    @else
                        <flux:text size="sm" class="text-zinc-400">{{ __('No description set.') }}</flux:text>
                    @endif
                </div>

                @if ($this->canManage)
                    <flux:button variant="subtle" size="sm" icon="pencil" wire:click="startEditing">
                        {{ __('Edit') }}
                    </flux:button>
                @endif
            </div>
        @endif
    </flux:card>

    {{-- Member events --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">{{ __('Events in this Project') }}</flux:heading>
        </div>

        @if ($this->memberEvents->isEmpty())
            <div class="rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600 p-8 text-center">
                <flux:text>{{ __('No events in this project yet.') }}</flux:text>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($this->memberEvents as $event)
                    <div class="flex items-center justify-between rounded-xl border border-zinc-200 dark:border-zinc-700 p-4" wire:key="member-{{ $event->id }}">
                        <a href="{{ route('events.show', $event) }}" wire:navigate class="flex items-center gap-3 hover:text-emerald-600 dark:hover:text-emerald-400">
                            <flux:icon name="calendar" variant="mini" class="size-5 text-zinc-400" />
                            <div>
                                <flux:heading size="sm">{{ $event->name }}</flux:heading>
                                <flux:text size="sm">{{ $event->starts_at->format('M d, Y g:i A') }}</flux:text>
                            </div>
                        </a>
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm" :color="match($event->status) {
                                \App\Enums\EventStatus::PublishedOpen => 'emerald',
                                \App\Enums\EventStatus::PublishedClosed => 'yellow',
                                \App\Enums\EventStatus::Draft => 'amber',
                                \App\Enums\EventStatus::Archived => 'zinc',
                            }">
                                {{ $event->status->label() }}
                            </flux:badge>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
