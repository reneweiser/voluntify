<div>
    @if ($expired)
        {{-- Expired token --}}
        <div class="rounded-lg p-8 text-center animate-fade-in-up" style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.2);">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full mb-4" style="background: rgba(245,158,11,0.15);">
                <flux:icon name="clock" class="size-10" style="color: #fbbf24;" />
            </div>
            <h2 class="font-bebas text-white text-2xl" style="letter-spacing: 0.04em;">{{ __('Link Expired') }}</h2>
            <p class="mt-2" style="color: #a1a1aa;">{{ __('This verification link has expired. Please sign up again to receive a new one.') }}</p>
        </div>
    @elseif ($verified && $newSignupCount > 0 && $skippedFullCount === 0)
        {{-- Full success --}}
        <div class="rounded-lg p-8 text-center animate-fade-in-up" style="background: rgba(5,150,105,0.1); border: 1px solid rgba(5,150,105,0.2);">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full mb-4" style="background: rgba(5,150,105,0.15);">
                <flux:icon name="check-circle" class="size-10" style="color: var(--brand);" />
            </div>
            <h2 class="font-bebas text-white text-2xl" style="letter-spacing: 0.04em;">{{ __('Email Verified — You\'re Signed Up!') }}</h2>
            <p class="mt-2" style="color: #a1a1aa;">{{ __('Check your email for a confirmation with your shift and ticket details.') }}</p>
        </div>
    @elseif ($verified && $newSignupCount > 0 && $skippedFullCount > 0)
        {{-- Partial success --}}
        <div class="rounded-lg p-8 text-center animate-fade-in-up" style="background: rgba(5,150,105,0.1); border: 1px solid rgba(5,150,105,0.2);">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full mb-4" style="background: rgba(5,150,105,0.15);">
                <flux:icon name="check-circle" class="size-10" style="color: var(--brand);" />
            </div>
            <h2 class="font-bebas text-white text-2xl" style="letter-spacing: 0.04em;">{{ __('Email Verified — You\'re Signed Up!') }}</h2>
            <p class="mt-2" style="color: #a1a1aa;">{{ __('Check your email for a confirmation with your shift and ticket details.') }}</p>
            <div class="mt-4 rounded-lg p-3 text-sm text-left" style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.2); color: #fbbf24;">
                {{ trans_choice(':count shift was full and could not be booked.|:count shifts were full and could not be booked.', $skippedFullCount, ['count' => $skippedFullCount]) }}
            </div>
        </div>
    @elseif ($verified && $newSignupCount === 0)
        {{-- All shifts full --}}
        <div class="rounded-lg p-8 text-center animate-fade-in-up" style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.2);">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full mb-4" style="background: rgba(245,158,11,0.15);">
                <flux:icon name="exclamation-triangle" class="size-10" style="color: #fbbf24;" />
            </div>
            <h2 class="font-bebas text-white text-2xl" style="letter-spacing: 0.04em;">{{ __('Email Verified') }}</h2>
            <p class="mt-2" style="color: #a1a1aa;">{{ __('Your email has been verified, but the selected shifts are now full.') }}</p>
            @if ($eventPublicToken)
                <div class="mt-4">
                    <a href="{{ route('events.public', $eventPublicToken) }}" class="public-btn-primary" style="text-decoration: none;">
                        {{ __('Back to Event Page') }}
                    </a>
                </div>
            @endif
        </div>
    @endif
</div>
