<div class="mx-auto max-w-7xl p-6">
    <div class="flex items-center gap-3 mb-4">
        <flux:button variant="ghost" icon="arrow-left" :href="route('events.show', $event)" wire:navigate />
        <flux:heading size="xl">{{ $event->name }}</flux:heading>
    </div>

    <x-events.tab-nav :event="$event" />

    {{-- Compose form --}}
    <flux:card class="mb-6">
        <flux:heading size="lg" class="mb-4">{{ __('Send Announcement') }}</flux:heading>
        <flux:text class="mb-4">
            {{ __('This will be emailed to :count active volunteer(s) for this event.', ['count' => $this->recipientCount]) }}
        </flux:text>

        <form wire:submit="send" class="space-y-4">
            <flux:field>
                <flux:label>{{ __('Subject') }}</flux:label>
                <flux:input wire:model="subject" />
                <flux:error name="subject" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Message') }}</flux:label>
                <flux:textarea wire:model="body" rows="4" />
                <flux:error name="body" />
            </flux:field>

            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="send">{{ __('Send Announcement') }}</span>
                <span wire:loading wire:target="send">{{ __('Sending...') }}</span>
            </flux:button>
        </form>
    </flux:card>

    {{-- History --}}
    <div>
        <flux:heading size="lg" class="mb-3">{{ __('Past Announcements') }}</flux:heading>

        @if ($this->history->isEmpty())
            <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('No announcements have been sent yet.') }}</flux:text>
        @else
            <div class="space-y-3">
                @foreach ($this->history as $announcement)
                    <div wire:key="announcement-{{ $announcement->id }}" class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $announcement->subject }}</div>
                                <flux:text class="mt-1">{{ $announcement->body }}</flux:text>
                            </div>
                        </div>
                        <div class="mt-2 text-xs text-zinc-400 dark:text-zinc-500">
                            {{ __('Sent by :name', ['name' => $announcement->sender?->name ?? __('Deleted user')]) }}
                            &middot; {{ $announcement->sent_at->diffForHumans() }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
