<div class="mx-auto max-w-7xl p-6">
    {{-- Welcome banner --}}
    <div class="mb-8 rounded-xl bg-gradient-to-r from-emerald-600 to-emerald-500 dark:from-emerald-700 dark:to-emerald-600 p-6 text-white shadow-sm">
        <flux:heading size="xl" class="!text-white">
            {{ __('Welcome back, :name!', ['name' => auth()->user()->name]) }}
        </flux:heading>

        @if ($this->organization)
            <div class="mt-2 flex items-center gap-2">
                <span class="text-emerald-100">{{ $this->organization->name }}</span>
                <flux:badge size="sm" class="!bg-white/20 !text-white !border-0">{{ __(ucfirst(str_replace('_', ' ', $this->userRole ?? ''))) }}</flux:badge>
            </div>
        @endif
    </div>

    {{-- Quick action cards --}}
    <div class="grid gap-6 md:grid-cols-3">
        <a href="{{ route('events.index') }}" wire:navigate
           class="group relative overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 card-accent-emerald card-gradient-emerald transition-all duration-200 hover:shadow-md">
            <div class="flex items-start gap-4">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400">
                    <flux:icon name="calendar" class="size-5" />
                </div>
                <div>
                    <flux:heading size="sm">{{ __('Events') }}</flux:heading>
                    <flux:text size="sm" class="mt-1">{{ __('Manage your upcoming events') }}</flux:text>
                </div>
            </div>
            <div class="mt-4 flex items-center text-sm font-medium text-emerald-600 dark:text-emerald-400 opacity-0 transition-opacity duration-200 group-hover:opacity-100">
                {{ __('View events') }}
                <flux:icon name="arrow-right" class="ml-1 size-4" />
            </div>
        </a>

        <div class="relative overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 card-accent-amber card-gradient-amber">
            <div class="flex items-start gap-4">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400">
                    <flux:icon name="user-group" class="size-5" />
                </div>
                <div>
                    <flux:heading size="sm">{{ __('Volunteers') }}</flux:heading>
                    <flux:text size="sm" class="mt-1">{{ __('Track your volunteer roster') }}</flux:text>
                </div>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 card-accent-sky card-gradient-sky">
            <div class="flex items-start gap-4">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-sky-100 dark:bg-sky-900/40 text-sky-600 dark:text-sky-400">
                    <flux:icon name="qr-code" class="size-5" />
                </div>
                <div>
                    <flux:heading size="sm">{{ __('Tickets') }}</flux:heading>
                    <flux:text size="sm" class="mt-1">{{ __('QR tickets and scanning') }}</flux:text>
                </div>
            </div>
        </div>
    </div>
</div>
