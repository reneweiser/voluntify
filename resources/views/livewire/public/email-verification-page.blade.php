<div>
    @if ($expired)
        {{-- Expired token --}}
        <div class="rounded-xl bg-gradient-to-br from-amber-50 to-amber-100 dark:from-amber-900/30 dark:to-amber-800/20 border border-amber-200 dark:border-amber-800 p-8 text-center">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/40">
                <flux:icon name="clock" class="size-10 text-amber-600 dark:text-amber-400" />
            </div>
            <flux:heading size="lg" class="mt-4">{{ __('Link Expired') }}</flux:heading>
            <flux:text class="mt-2">{{ __('This verification link has expired. Please sign up again to receive a new one.') }}</flux:text>
        </div>
    @elseif ($verified && $newSignupCount > 0 && $skippedFullCount === 0)
        {{-- Full success --}}
        <div class="rounded-xl bg-gradient-to-br from-emerald-50 to-emerald-100 dark:from-emerald-900/30 dark:to-emerald-800/20 border border-emerald-200 dark:border-emerald-800 p-8 text-center">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40">
                <flux:icon name="check-circle" class="size-10 text-emerald-600 dark:text-emerald-400" />
            </div>
            <flux:heading size="lg" class="mt-4">{{ __('Email Verified — You\'re Signed Up!') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Check your email for a confirmation with your shift and ticket details.') }}</flux:text>
        </div>
    @elseif ($verified && $newSignupCount > 0 && $skippedFullCount > 0)
        {{-- Partial success --}}
        <div class="rounded-xl bg-gradient-to-br from-emerald-50 to-emerald-100 dark:from-emerald-900/30 dark:to-emerald-800/20 border border-emerald-200 dark:border-emerald-800 p-8 text-center">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40">
                <flux:icon name="check-circle" class="size-10 text-emerald-600 dark:text-emerald-400" />
            </div>
            <flux:heading size="lg" class="mt-4">{{ __('Email Verified — You\'re Signed Up!') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Check your email for a confirmation with your shift and ticket details.') }}</flux:text>
            <flux:callout variant="warning" class="mt-4">
                {{ trans_choice(':count shift was full and could not be booked.|:count shifts were full and could not be booked.', $skippedFullCount, ['count' => $skippedFullCount]) }}
            </flux:callout>
        </div>
    @elseif ($verified && $newSignupCount === 0)
        {{-- All shifts full --}}
        <div class="rounded-xl bg-gradient-to-br from-amber-50 to-amber-100 dark:from-amber-900/30 dark:to-amber-800/20 border border-amber-200 dark:border-amber-800 p-8 text-center">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/40">
                <flux:icon name="exclamation-triangle" class="size-10 text-amber-600 dark:text-amber-400" />
            </div>
            <flux:heading size="lg" class="mt-4">{{ __('Email Verified') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Your email has been verified, but the selected shifts are now full.') }}</flux:text>
            @if ($eventPublicToken)
                <div class="mt-4">
                    <flux:button :href="route('events.public', $eventPublicToken)" variant="primary">
                        {{ __('Back to Event Page') }}
                    </flux:button>
                </div>
            @endif
        </div>
    @endif
</div>
