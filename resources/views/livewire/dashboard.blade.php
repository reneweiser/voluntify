<div class="mx-auto max-w-7xl p-6">
    <div class="mb-6">
        <flux:heading size="xl">
            {{ __('Welcome back, :name!', ['name' => auth()->user()->name]) }}
        </flux:heading>

        @if ($this->organization)
            <div class="mt-2 flex items-center gap-2">
                <flux:text>{{ $this->organization->name }}</flux:text>
                <flux:badge size="sm">{{ __(ucfirst(str_replace('_', ' ', $this->userRole ?? ''))) }}</flux:badge>
            </div>
        @endif
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="relative aspect-video overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="sm">{{ __('Events') }}</flux:heading>
            <flux:text size="sm" class="mt-1">{{ __('Manage your upcoming events') }}</flux:text>
        </div>
        <div class="relative aspect-video overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="sm">{{ __('Volunteers') }}</flux:heading>
            <flux:text size="sm" class="mt-1">{{ __('Track your volunteer roster') }}</flux:text>
        </div>
        <div class="relative aspect-video overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="sm">{{ __('Tickets') }}</flux:heading>
            <flux:text size="sm" class="mt-1">{{ __('QR tickets and scanning') }}</flux:text>
        </div>
    </div>
</div>
