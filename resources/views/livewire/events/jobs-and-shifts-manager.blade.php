<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center gap-3 mb-4">
        <flux:button variant="ghost" icon="arrow-left" :href="route('events.index')" wire:navigate aria-label="{{ __('Back to events') }}" />
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
                                <div class="flex items-center gap-2">
                                    <flux:heading size="sm">{{ $job->name }}</flux:heading>
                                    @unless ($job->is_active)
                                        <flux:badge size="sm" color="zinc">{{ __('Inactive') }}</flux:badge>
                                    @endunless
                                </div>
                                @if ($job->description)
                                    <flux:text size="sm" class="mt-1">{{ $job->description }}</flux:text>
                                @endif
                            </div>
                            @if ($this->canManage)
                                <div class="flex items-center gap-2">
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="power"
                                        wire:click="toggleJobActive({{ $job->id }})"
                                        wire:confirm="{{ $job->is_active ? __('Deactivate this job? It will disappear from public signup but remain available for existing signups.') : __('Reactivate this job for public signup?') }}"
                                    />
                                    <flux:button variant="ghost" size="sm" icon="pencil" wire:click="openEditJob({{ $job->id }})" />
                                    <flux:button variant="ghost" size="sm" icon="square-2-stack"
                                        wire:click="cloneJob({{ $job->id }})"
                                        wire:confirm="{{ __('Duplicate this job and all its shifts?') }}"
                                        wire:loading.attr="disabled" />
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
                                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                                        @if ($this->canManage)
                                            <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                                        @endif
                                    </flux:table.columns>
                                    <flux:table.rows>
                                        @foreach ($job->shifts as $shift)
                                            <flux:table.row :key="'shift-'.$shift->id">
                                                <flux:table.cell>
                                                    @php $tz = $event->project->timezone ?? 'UTC'; @endphp
                                                    {{ $shift->shift_date->setTimezone($tz)->format('M d') }} — {{ $shift->displayTimeRange($tz) }}
                                                </flux:table.cell>
                                                <flux:table.cell>
                                                    {{ $shift->signups_count }} / {{ $shift->capacity }}
                                                </flux:table.cell>
                                                <flux:table.cell>
                                                    @if (! $shift->is_active)
                                                        <flux:badge size="sm" color="zinc">{{ __('Inactive') }}</flux:badge>
                                                    @elseif ($shift->signups_count >= $shift->capacity)
                                                        <flux:badge size="sm" color="red">{{ __('Full') }}</flux:badge>
                                                    @else
                                                        <flux:badge size="sm" color="emerald">{{ __('Open') }}</flux:badge>
                                                    @endif
                                                </flux:table.cell>
                                                @if ($this->canManage)
                                                    <flux:table.cell align="end">
                                                        <div class="flex items-center justify-end gap-1">
                                                            <flux:button
                                                                variant="ghost"
                                                                size="sm"
                                                                icon="power"
                                                                wire:click="toggleShiftActive({{ $shift->id }})"
                                                                wire:confirm="{{ $shift->is_active ? __('Deactivate this shift? It will disappear from public signup but remain on existing signups.') : __('Reactivate this shift for public signup?') }}"
                                                            />
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

                <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <div>
                        <flux:text class="font-medium">{{ __('Active') }}</flux:text>
                        <flux:text size="sm">{{ __('Inactive jobs stay visible in admin but are hidden from public signup.') }}</flux:text>
                    </div>
                    <flux:switch wire:model="jobIsActive" />
                </div>

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
                    <flux:label>{{ __('Date') }}</flux:label>
                    <flux:input type="date" wire:model="shiftDate" />
                    <flux:error name="shiftDate" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Starts At') }} <span class="text-zinc-400 font-normal">({{ __('optional') }})</span></flux:label>
                    <flux:input type="datetime-local" wire:model.live="shiftStartsAt" />
                    <flux:error name="shiftStartsAt" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Ends At') }} <span class="text-zinc-400 font-normal">({{ __('optional') }})</span></flux:label>
                    <flux:input type="datetime-local" wire:model="shiftEndsAt" />
                    <flux:error name="shiftEndsAt" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Display Text') }}
                        @if (empty($shiftStartsAt))
                            <span class="text-red-500 font-normal">*</span>
                        @else
                            <span class="text-zinc-400 font-normal">({{ __('optional') }})</span>
                        @endif
                    </flux:label>
                    <flux:input wire:model="shiftDisplayText" placeholder="{{ __('e.g. Ganzer Tag, Flexibel') }}" />
                    <flux:description>{{ __('Shown instead of times when no start/end time is set.') }}</flux:description>
                    <flux:error name="shiftDisplayText" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Capacity') }}</flux:label>
                    <flux:input type="number" wire:model="shiftCapacity" min="1" />
                    <flux:error name="shiftCapacity" />
                </flux:field>

                <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <div>
                        <flux:text class="font-medium">{{ __('Active') }}</flux:text>
                        <flux:text size="sm">{{ __('Inactive shifts stay visible in admin but are hidden from public signup.') }}</flux:text>
                    </div>
                    <flux:switch wire:model="shiftIsActive" />
                </div>

                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">{{ $editingShiftId ? __('Save Changes') : __('Add Shift') }}</flux:button>
                </div>
            </form>
        </flux:modal>
        @endif
    </x-events.layout>
</div>
