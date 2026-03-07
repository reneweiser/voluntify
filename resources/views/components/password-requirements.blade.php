@php
    $rules = \Illuminate\Validation\Rules\Password::default()?->appliedRules();

    $hasRules = $rules && ($rules['mixedCase'] || $rules['letters'] || $rules['numbers'] || $rules['symbols'] || $rules['uncompromised']);
@endphp

@if ($hasRules)
    <div
        wire:ignore
        x-data="{
            password: '',
            get hasMinLength() { return this.password.length >= {{ $rules['min'] }}; },
            get hasMixedCase() { return /[a-z]/.test(this.password) && /[A-Z]/.test(this.password); },
            get hasLetters() { return /[a-zA-Z]/.test(this.password); },
            get hasNumbers() { return /\d/.test(this.password); },
            get hasSymbols() { return /[^a-zA-Z0-9]/.test(this.password); },
        }"
        x-on:input.window="if ($event.target.matches('input[data-password-target]')) password = $event.target.value"
        x-on:submit.window="if ($event.target.matches('form')) password = ''"
        class="mt-2 space-y-1.5 text-sm"
    >
        <div :class="hasMinLength ? 'text-emerald-400' : 'text-zinc-500'" class="flex items-center gap-1.5 transition-colors duration-200">
            <svg x-show="hasMinLength" xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" /></svg>
            <svg x-show="!hasMinLength" xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><circle cx="10" cy="10" r="7" fill="none" stroke="currentColor" stroke-width="1.5" /></svg>
            <span>{{ __('At least :count characters', ['count' => $rules['min']]) }}</span>
        </div>

        @if ($rules['mixedCase'])
            <div :class="hasMixedCase ? 'text-emerald-400' : 'text-zinc-500'" class="flex items-center gap-1.5 transition-colors duration-200">
                <svg x-show="hasMixedCase" xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" /></svg>
                <svg x-show="!hasMixedCase" xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><circle cx="10" cy="10" r="7" fill="none" stroke="currentColor" stroke-width="1.5" /></svg>
                <span>{{ __('Upper and lowercase letters') }}</span>
            </div>
        @endif

        @if ($rules['numbers'])
            <div :class="hasNumbers ? 'text-emerald-400' : 'text-zinc-500'" class="flex items-center gap-1.5 transition-colors duration-200">
                <svg x-show="hasNumbers" xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" /></svg>
                <svg x-show="!hasNumbers" xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><circle cx="10" cy="10" r="7" fill="none" stroke="currentColor" stroke-width="1.5" /></svg>
                <span>{{ __('At least one number') }}</span>
            </div>
        @endif

        @if ($rules['symbols'])
            <div :class="hasSymbols ? 'text-emerald-400' : 'text-zinc-500'" class="flex items-center gap-1.5 transition-colors duration-200">
                <svg x-show="hasSymbols" xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" /></svg>
                <svg x-show="!hasSymbols" xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><circle cx="10" cy="10" r="7" fill="none" stroke="currentColor" stroke-width="1.5" /></svg>
                <span>{{ __('At least one symbol') }}</span>
            </div>
        @endif

        @if ($rules['uncompromised'])
            <div class="flex items-center gap-1.5 text-zinc-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" /></svg>
                <span>{{ __('Must not be a known compromised password') }}</span>
            </div>
        @endif
    </div>
@endif
