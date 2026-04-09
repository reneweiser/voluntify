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
    @elseif ($alreadyVerified)
        {{-- Already verified (re-used link) --}}
        <div class="rounded-lg p-8 text-center animate-fade-in-up" style="background: rgba(5,150,105,0.1); border: 1px solid rgba(5,150,105,0.2);">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full mb-4" style="background: rgba(5,150,105,0.15);">
                <flux:icon name="check-circle" class="size-10" style="color: var(--brand);" />
            </div>
            <h2 class="font-bebas text-white text-2xl" style="letter-spacing: 0.04em;">{{ __('Already Verified') }}</h2>
            <p class="mt-2" style="color: #a1a1aa;">{{ __('Your email has already been verified. You can close this window or return to the signup page.') }}</p>
            @if ($continueSignupUrl)
                <div class="mt-4">
                    <a href="{{ $continueSignupUrl }}" class="public-btn-primary" style="text-decoration: none;">
                        {{ __('Continue Signup') }}
                    </a>
                </div>
            @endif
        </div>
    @elseif ($verified)
        {{-- Newly verified --}}
        <div class="rounded-lg p-8 text-center animate-fade-in-up" style="background: rgba(5,150,105,0.1); border: 1px solid rgba(5,150,105,0.2);">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full mb-4" style="background: rgba(5,150,105,0.15);">
                <flux:icon name="check-circle" class="size-10" style="color: var(--brand);" />
            </div>
            <h2 class="font-bebas text-white text-2xl" style="letter-spacing: 0.04em;">{{ __('Email Verified') }}</h2>
            <p class="mt-2" style="color: #a1a1aa;">{{ __('Your email has been verified! You can now return to the signup page to continue.') }}</p>
            @if ($continueSignupUrl)
                <div class="mt-4">
                    <a href="{{ $continueSignupUrl }}" class="public-btn-primary" style="text-decoration: none;">
                        {{ __('Continue Signup') }}
                    </a>
                </div>
            @endif
        </div>
    @endif
</div>
