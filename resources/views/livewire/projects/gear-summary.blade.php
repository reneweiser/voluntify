<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <flux:button variant="ghost" icon="arrow-left" :href="route('projects.index')" wire:navigate aria-label="{{ __('Back to projects') }}" />
            <flux:heading size="xl">{{ $project->name }}</flux:heading>
        </div>
        @can('update', $project)
            <flux:button variant="subtle" icon="arrow-down-tray" wire:click="exportCsv">
                {{ __('CSV exportieren') }}
            </flux:button>
        @endcan
    </div>

    <x-projects.layout :project="$project">

    {{-- Stats cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <flux:card size="sm">
            <div class="flex items-center gap-3">
                <span class="size-2.5 shrink-0 rounded-full bg-zinc-400"></span>
                <div>
                    <flux:text size="sm">{{ __('Gesamt zugewiesen') }}</flux:text>
                    <flux:heading size="xl">{{ $this->totalAssigned }}</flux:heading>
                </div>
            </div>
        </flux:card>
        <flux:card size="sm">
            <div class="flex items-center gap-3">
                <span class="size-2.5 shrink-0 rounded-full bg-emerald-500"></span>
                <div>
                    <flux:text size="sm">{{ __('Abgeholt') }}</flux:text>
                    <flux:heading size="xl">{{ $this->totalPickedUp }}</flux:heading>
                </div>
            </div>
        </flux:card>
        <flux:card size="sm">
            <div class="flex items-center gap-3">
                <span class="size-2.5 shrink-0 rounded-full bg-amber-500"></span>
                <div>
                    <flux:text size="sm">{{ __('Ausstehend') }}</flux:text>
                    <flux:heading size="xl">{{ $this->totalPending }}</flux:heading>
                </div>
            </div>
        </flux:card>
    </div>

    {{-- Per-item breakdown --}}
    @if (empty($this->summary))
        <div class="rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600 p-12 text-center">
            <flux:heading size="sm">{{ __('Keine Gear-Artikel konfiguriert') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Füge zuerst Gear-Artikel zum Projekt hinzu.') }}</flux:text>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($this->summary as $item)
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4" wire:key="gear-{{ $item['id'] }}">
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="sm">{{ $item['name'] }}</flux:heading>
                            @if ($item['requires_size'])
                                <flux:badge size="sm" color="sky">{{ __('Größenauswahl') }}</flux:badge>
                            @endif
                        </div>
                        <div class="flex items-center gap-4 text-sm">
                            <div class="text-center">
                                <flux:text size="sm">{{ __('Zugewiesen') }}</flux:text>
                                <flux:text class="font-semibold">{{ $item['total_assigned'] }}</flux:text>
                            </div>
                            <div class="text-center">
                                <flux:text size="sm" class="text-emerald-600">{{ __('Abgeholt') }}</flux:text>
                                <flux:text class="font-semibold text-emerald-600">{{ $item['picked_up'] }}</flux:text>
                            </div>
                            <div class="text-center">
                                <flux:text size="sm" class="text-amber-600">{{ __('Ausstehend') }}</flux:text>
                                <flux:text class="font-semibold text-amber-600">{{ $item['pending'] }}</flux:text>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
    </x-projects.layout>
</div>
