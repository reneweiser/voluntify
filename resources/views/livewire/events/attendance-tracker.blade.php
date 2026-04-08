<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center gap-3 mb-4">
        <flux:button variant="ghost" icon="arrow-left" :href="route('events.index')" wire:navigate aria-label="{{ __('Back to events') }}" />
        <flux:heading size="xl">{{ $event->name }}</flux:heading>
    </div>

    <x-events.layout :event="$event">
        @if (session('conflict'))
            <flux:callout variant="warning" class="mb-4">{{ session('conflict') }}</flux:callout>
        @endif

        <div class="flex items-center justify-between mb-6">
            <flux:heading size="lg">{{ __('Attendance') }}</flux:heading>
        </div>

        {{-- Shift selector --}}
        <div class="mb-6">
            <flux:select wire:model.live="selectedShiftId" placeholder="{{ __('Select a shift...') }}">
                @foreach ($this->shifts as $shift)
                    <flux:select.option :value="$shift->id" wire:key="shift-option-{{ $shift->id }}">
                        @php $tz = $event->project->timezone ?? 'UTC'; @endphp
                        {{ $shift->job_name }}: {{ $shift->shift_date->setTimezone($tz)->format('M d') }} — {{ $shift->displayTimeRange($tz) }}
                        ({{ $shift->attended_count }}/{{ $shift->signups_count }} {{ __('marked') }})
                    </flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($selectedShiftId)
            @if ($this->signups->isEmpty())
                <div class="rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600 p-12 text-center">
                    <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <flux:icon name="users" class="size-8 text-zinc-400" />
                    </div>
                    <flux:heading size="sm" class="mt-4">{{ __('No signups for this shift') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('No volunteers have signed up for this shift yet.') }}</flux:text>
                </div>
            @else
                <div class="overflow-x-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Volunteer') }}</flux:table.column>
                        <flux:table.column>{{ __('Email') }}</flux:table.column>
                        <flux:table.column>{{ __('Arrived') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->signups as $signup)
                            <flux:table.row :key="'signup-'.$signup->id">
                                <flux:table.cell>{{ $signup->volunteer->full_name }}</flux:table.cell>
                                <flux:table.cell>{{ $signup->volunteer->email }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($signup->volunteer->eventArrivals->isNotEmpty())
                                        <flux:badge size="sm" color="emerald">{{ __('Yes') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="zinc">{{ __('No') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($signup->attendanceRecord)
                                        @php
                                            $color = match($signup->attendanceRecord->status) {
                                                \App\Enums\AttendanceStatus::OnTime => 'emerald',
                                                \App\Enums\AttendanceStatus::Late => 'amber',
                                                \App\Enums\AttendanceStatus::NoShow => 'red',
                                                default => 'zinc',
                                            };
                                        @endphp
                                        <flux:badge size="sm" :color="$color">{{ $signup->attendanceRecord->status->name }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="zinc">{{ __('Unmarked') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    <div class="flex items-center justify-end gap-1">
                                        @foreach ($this->attendanceStates as $state)
                                            @php
                                                $isActive = $signup->attendanceRecord?->status?->value === $state['key'];
                                                $variant = match(true) {
                                                    $isActive && $state['key'] === 'no_show' => 'danger',
                                                    $isActive => 'primary',
                                                    default => 'ghost',
                                                };
                                                $icon = match($state['key']) {
                                                    'on_time' => 'check',
                                                    'late' => 'clock',
                                                    'no_show' => 'x-mark',
                                                    'en_route' => 'arrow-right',
                                                    'excused' => 'shield-check',
                                                    default => 'tag',
                                                };
                                            @endphp
                                            <flux:button
                                                size="xs"
                                                :variant="$variant"
                                                wire:click="markStatus({{ $signup->id }}, '{{ $state['key'] }}')"
                                                title="{{ $state['label'] }}"
                                            >
                                                <flux:icon :name="$icon" class="size-4 sm:hidden" />
                                                <span class="hidden sm:inline">{{ $state['label'] }}</span>
                                            </flux:button>
                                        @endforeach
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
                </div>
            @endif
        @else
            <div class="rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600 p-12 text-center">
                <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/40">
                    <flux:icon name="clipboard-document-check" class="size-8 text-amber-600 dark:text-amber-400" />
                </div>
                <flux:heading size="sm" class="mt-4">{{ __('Select a shift') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Choose a shift from the dropdown above to start tracking attendance.') }}</flux:text>
            </div>
        @endif
    </x-events.layout>
</div>
