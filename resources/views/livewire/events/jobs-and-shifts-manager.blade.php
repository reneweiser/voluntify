<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center gap-3 mb-4">
        <flux:button variant="ghost" icon="arrow-left" :href="route('events.index')" wire:navigate />
        <flux:heading size="xl">{{ $event->name }}</flux:heading>
    </div>

    <x-events.layout :event="$event">
        @error('job')
            <flux:callout variant="danger" class="mb-4">{{ $message }}</flux:callout>
        @enderror

        @error('shift')
            <flux:callout variant="danger" class="mb-4">{{ $message }}</flux:callout>
        @enderror

        <div class="flex items-center justify-between mb-6">
            <flux:heading size="lg">{{ __('Volunteer Jobs') }}</flux:heading>

            @if ($this->canManage)
                <flux:button variant="primary" icon="plus" size="sm" wire:click="openCreateJob">
                    {{ __('Add Job') }}
                </flux:button>
            @endif
        </div>

        @if ($this->jobs->isEmpty())
            <div class="rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600 p-12 text-center">
                <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/40">
                    <flux:icon name="briefcase" class="size-8 text-amber-600 dark:text-amber-400" />
                </div>
                <flux:heading size="sm" class="mt-4">{{ __('No jobs yet') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Add volunteer jobs and their shifts to get started.') }}</flux:text>
                @if ($this->canManage)
                    <div class="mt-4">
                        <flux:button variant="primary" size="sm" icon="plus" wire:click="openCreateJob">
                            {{ __('Add Job') }}
                        </flux:button>
                    </div>
                @endif
            </div>
        @else
            <div class="space-y-6">
                @foreach ($this->jobs as $job)
                    <flux:card class="!p-0 overflow-hidden" wire:key="job-{{ $job->id }}">
                        {{-- Job header --}}
                        <div class="flex flex-wrap items-center justify-between gap-2 p-4 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                            <div>
                                <flux:heading size="sm">{{ $job->name }}</flux:heading>
                                @if ($job->description)
                                    <flux:text size="sm" class="mt-1">{{ $job->description }}</flux:text>
                                @endif
                            </div>
                            @if ($this->canManage)
                                <div class="flex items-center gap-2">
                                    <flux:button variant="ghost" size="sm" icon="pencil" wire:click="openEditJob({{ $job->id }})" />
                                    <flux:button variant="ghost" size="sm" icon="trash" wire:click="deleteJob({{ $job->id }})"
                                        wire:confirm="{{ __('Delete this job and all its shifts?') }}" />
                                </div>
                            @endif
                        </div>

                        {{-- Shifts table --}}
                        <div class="p-4">
                            @if ($job->shifts->isEmpty())
                                <flux:text size="sm" variant="subtle" class="italic">{{ __('No shifts added yet.') }}</flux:text>
                            @else
                                <div class="overflow-x-auto">
                                <flux:table>
                                    <flux:table.columns>
                                        <flux:table.column>{{ __('Time') }}</flux:table.column>
                                        <flux:table.column>{{ __('Signups') }}</flux:table.column>
                                        <flux:table.column>{{ __('Capacity') }}</flux:table.column>
                                        @if ($this->canManage)
                                            <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                                        @endif
                                    </flux:table.columns>
                                    <flux:table.rows>
                                        @foreach ($job->shifts as $shift)
                                            <flux:table.row :key="'shift-'.$shift->id">
                                                <flux:table.cell>
                                                    {{ $shift->starts_at->format('M d, g:i A') }} &mdash; {{ $shift->ends_at->format('g:i A') }}
                                                </flux:table.cell>
                                                <flux:table.cell>
                                                    {{ $shift->signups_count }} / {{ $shift->capacity }}
                                                </flux:table.cell>
                                                <flux:table.cell>
                                                    @if ($shift->signups_count >= $shift->capacity)
                                                        <flux:badge size="sm" color="red">{{ __('Full') }}</flux:badge>
                                                    @else
                                                        <flux:badge size="sm" color="emerald">{{ __('Open') }}</flux:badge>
                                                    @endif
                                                </flux:table.cell>
                                                @if ($this->canManage)
                                                    <flux:table.cell align="end">
                                                        <div class="flex items-center justify-end gap-1">
                                                            <flux:button variant="ghost" size="sm" icon="pencil" wire:click="openEditShift({{ $shift->id }})" />
                                                            <flux:button variant="ghost" size="sm" icon="trash" wire:click="deleteShift({{ $shift->id }})"
                                                                wire:confirm="{{ __('Delete this shift?') }}" />
                                                        </div>
                                                    </flux:table.cell>
                                                @endif
                                            </flux:table.row>
                                        @endforeach
                                    </flux:table.rows>
                                </flux:table>
                                </div>
                            @endif

                            @if ($this->canManage)
                                <div class="mt-3">
                                    <flux:button variant="subtle" size="sm" icon="plus" wire:click="openCreateShift({{ $job->id }})">
                                        {{ __('Add Shift') }}
                                    </flux:button>
                                </div>
                            @endif
                        </div>
                    </flux:card>
                @endforeach
            </div>
        @endif

        {{-- Job Modal --}}
        @if ($this->canManage)
        <flux:modal wire:model="showJobModal" class="md:w-96">
            <form wire:submit="saveJob" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $editingJobId ? __('Edit Job') : __('Add Job') }}</flux:heading>
                </div>

                <flux:field>
                    <flux:label>{{ __('Job Name') }}</flux:label>
                    <flux:input wire:model="jobName" placeholder="{{ __('e.g. Ticket Scanner') }}" />
                    <flux:error name="jobName" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Description') }}</flux:label>
                    <flux:textarea wire:model="jobDescription" rows="2" />
                    <flux:error name="jobDescription" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Instructions') }}</flux:label>
                    <flux:textarea wire:model="jobInstructions" rows="2" />
                    <flux:error name="jobInstructions" />
                </flux:field>

                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">{{ $editingJobId ? __('Save Changes') : __('Add Job') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        {{-- Shift Modal --}}
        <flux:modal wire:model="showShiftModal" class="md:w-96">
            <form wire:submit="saveShift" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $editingShiftId ? __('Edit Shift') : __('Add Shift') }}</flux:heading>
                </div>

                <flux:field>
                    <flux:label>{{ __('Starts At') }}</flux:label>
                    <flux:input type="datetime-local" wire:model="shiftStartsAt" />
                    <flux:error name="shiftStartsAt" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Ends At') }}</flux:label>
                    <flux:input type="datetime-local" wire:model="shiftEndsAt" />
                    <flux:error name="shiftEndsAt" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Capacity') }}</flux:label>
                    <flux:input type="number" wire:model="shiftCapacity" min="1" />
                    <flux:error name="shiftCapacity" />
                </flux:field>

                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">{{ $editingShiftId ? __('Save Changes') : __('Add Shift') }}</flux:button>
                </div>
            </form>
        </flux:modal>
        @endif
    </x-events.layout>
</div>
