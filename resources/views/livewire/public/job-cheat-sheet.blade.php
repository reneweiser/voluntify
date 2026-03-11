<div>
    <div class="mb-6">
        <flux:button variant="subtle" size="sm" icon="arrow-left" :href="route('events.public', $event->public_token)">
            {{ __('Back to Event') }}
        </flux:button>
    </div>

    <div class="mb-8">
        <flux:heading size="xl">{{ $job->name }}</flux:heading>
        <flux:text class="mt-1">{{ $event->name }}</flux:text>
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6">
        <flux:text class="whitespace-pre-line">{{ $job->instructions }}</flux:text>
    </div>
</div>
