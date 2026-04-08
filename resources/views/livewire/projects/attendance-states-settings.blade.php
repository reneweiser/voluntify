<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center gap-3 mb-4">
        <flux:button variant="ghost" icon="arrow-left" :href="route('projects.show', $project)" wire:navigate aria-label="{{ __('Back to project') }}" />
        <flux:heading size="xl">{{ $project->name }}</flux:heading>
    </div>

    <x-projects.layout :project="$project">
        <div class="flex items-center justify-between mb-6">
            <flux:heading size="lg">{{ __('Attendance States') }}</flux:heading>
        </div>

        <flux:text class="mb-6">{{ __('Customize the labels and availability of attendance states for this project. Core states cannot be deactivated.') }}</flux:text>

        <div class="space-y-3">
            @foreach ($states as $i => $state)
                <flux:card wire:key="state-{{ $state['key'] }}">
                    <div class="flex items-center gap-4">
                        <div class="flex-1">
                            <flux:field>
                                <flux:label>{{ $state['key'] }}</flux:label>
                                <flux:input wire:model="states.{{ $i }}.label" placeholder="{{ $state['key'] }}" />
                                <flux:error name="states.{{ $i }}.label" />
                            </flux:field>
                        </div>

                        <div class="flex items-center gap-2">
                            @if ($state['core'])
                                <flux:badge size="sm" color="blue">{{ __('Core') }}</flux:badge>
                            @else
                                <flux:checkbox
                                    wire:model="states.{{ $i }}.active"
                                    label="{{ __('Active') }}"
                                />
                            @endif
                            <flux:error name="states.{{ $i }}.active" />
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>

        <div class="mt-6 flex justify-end">
            <flux:button variant="primary" wire:click="save">{{ __('Save') }}</flux:button>
        </div>
    </x-projects.layout>
</div>
