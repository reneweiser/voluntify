<div>
    <div class="mb-6">
        <a href="{{ route('events.public', $event->public_token) }}" class="inline-flex items-center gap-1.5 text-sm" style="color: #a1a1aa; text-decoration: none; transition: color 0.2s;">
            <flux:icon name="arrow-left" variant="mini" class="size-4" />
            {{ __('Back to Event') }}
        </a>
    </div>

    <div class="mb-8">
        <h1 class="font-bebas text-white leading-none" style="font-size: clamp(1.8rem, 4vw, 2.5rem); letter-spacing: 0.04em;">{{ $job->name }}</h1>
        <div class="accent-bar mt-3"><span></span><span></span><span></span></div>
        <p class="mt-3" style="color: #a1a1aa;">{{ $event->name }}</p>
    </div>

    <div class="rounded-lg p-6" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);">
        <p class="whitespace-pre-line" style="color: #a1a1aa; line-height: 1.7;">{{ $job->instructions }}</p>
    </div>
</div>
