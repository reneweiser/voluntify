<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center justify-between mb-6">
        <flux:heading size="xl">{{ __('Projects') }}</flux:heading>

        @if ($this->canCreateProjects)
            <flux:button variant="primary" icon="plus" wire:click="$set('showCreateModal', true)">
                {{ __('Create Project') }}
            </flux:button>
        @endif
    </div>

    {{-- Projects list --}}
    @if ($this->projects->isEmpty())
        <div class="rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600 p-12 text-center">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40">
                <flux:icon name="folder" class="size-8 text-emerald-600 dark:text-emerald-400" />
            </div>
            <flux:heading size="sm" class="mt-4">{{ __('No projects yet') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Create a project to organize related events together.') }}</flux:text>
            @if ($this->canCreateProjects)
                <div class="mt-4">
                    <flux:button variant="primary" size="sm" icon="plus" wire:click="$set('showCreateModal', true)">
                        {{ __('Create Project') }}
                    </flux:button>
                </div>
            @endif
        </div>
    @else
        <div class="flex flex-col gap-4">
            @foreach ($this->projects as $project)
                <a href="{{ route('projects.show', $project) }}" wire:navigate wire:key="project-{{ $project->id }}" aria-label="{{ $project->name }}">
                    <flux:card size="sm" class="hover:bg-zinc-50 dark:hover:bg-zinc-700">
                        <div class="flex items-start gap-3">
                            @if ($project->titleImageUrl())
                                <img src="{{ $project->titleImageUrl() }}" alt="" class="size-14 shrink-0 rounded-lg object-cover" />
                            @endif
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-3">
                                    <flux:heading size="sm" class="truncate">{{ $project->name }}</flux:heading>
                                    <flux:badge size="sm" class="shrink-0" color="zinc">
                                        {{ $project->events_count }} {{ __('events') }}
                                    </flux:badge>
                                </div>
                                @if ($project->description)
                                    <flux:text size="sm" class="mt-1 line-clamp-1">{{ $project->description }}</flux:text>
                                @endif
                            </div>
                        </div>
                    </flux:card>
                </a>
            @endforeach
        </div>
    @endif

    {{-- Create Project Modal --}}
    @if ($this->canCreateProjects)
    <flux:modal wire:model.self="showCreateModal" class="md:w-96">
        <form wire:submit="createProject" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Create Project') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Group related events under a shared landing page.') }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Project Name') }}</flux:label>
                <flux:input wire:model="projectName" placeholder="{{ __('e.g. SKHC Festival') }}" />
                <flux:error name="projectName" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Description') }}</flux:label>
                <flux:textarea wire:model="projectDescription" rows="3" />
                <flux:error name="projectDescription" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Title Image') }} <span class="text-zinc-400 font-normal">({{ __('optional') }})</span></flux:label>
                <flux:input type="file" wire:model="projectTitleImage" accept="image/jpeg,image/png,image/webp" />
                <flux:error name="projectTitleImage" />
            </flux:field>

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">{{ __('Create Project') }}</flux:button>
            </div>
        </form>
    </flux:modal>
    @endif
</div>
