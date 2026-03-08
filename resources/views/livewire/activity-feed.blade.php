<div class="mx-auto max-w-7xl p-6">
    <flux:heading size="xl" class="mb-6">{{ __('Activity Log') }}</flux:heading>

    <div class="flex flex-wrap items-end gap-3 mb-6">
        <div class="w-48">
            <flux:select wire:model.live="eventFilter" placeholder="{{ __('All Events') }}">
                <flux:select.option value="">{{ __('All Events') }}</flux:select.option>
                @foreach ($this->events as $event)
                    <flux:select.option :value="$event->id">{{ $event->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="w-40">
            <flux:select wire:model.live="categoryFilter" placeholder="{{ __('All Categories') }}">
                <flux:select.option value="">{{ __('All Categories') }}</flux:select.option>
                @foreach (\App\Enums\ActivityCategory::cases() as $category)
                    <flux:select.option :value="$category->value">{{ $category->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="w-48">
            <flux:select wire:model.live="actorFilter" placeholder="{{ __('All Members') }}">
                <flux:select.option value="">{{ __('All Members') }}</flux:select.option>
                @foreach ($this->members as $member)
                    <flux:select.option :value="$member->id">{{ $member->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="w-40">
            <flux:input type="date" wire:model.live="dateFrom" label="{{ __('From') }}" />
        </div>

        <div class="w-40">
            <flux:input type="date" wire:model.live="dateTo" label="{{ __('To') }}" />
        </div>

        @if ($eventFilter || $categoryFilter || $actorFilter || $dateFrom || $dateTo)
            <flux:button variant="subtle" size="sm" wire:click="clearFilters" icon="x-mark">
                {{ __('Clear') }}
            </flux:button>
        @endif
    </div>

    @if ($this->activities->isEmpty())
        <div class="rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600 p-12 text-center">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                <flux:icon name="clipboard-document-list" class="size-8 text-zinc-400" />
            </div>
            <flux:heading size="sm" class="mt-4">{{ __('No activity recorded yet') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Activities will appear here as actions are taken across the organization.') }}</flux:text>
        </div>
    @else
        <div class="overflow-x-auto">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('When') }}</flux:table.column>
                <flux:table.column>{{ __('Who') }}</flux:table.column>
                <flux:table.column>{{ __('Category') }}</flux:table.column>
                <flux:table.column>{{ __('Description') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->activities as $activity)
                    <flux:table.row :key="'activity-'.$activity->id">
                        <flux:table.cell class="whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $activity->created_at->diffForHumans() }}
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">
                            {{ $activity->causer?->name ?? __('System') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$activity->category->color()">
                                {{ $activity->category->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $activity->description }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
        </div>

        <div class="mt-4">
            {{ $this->activities->links() }}
        </div>
    @endif
</div>
