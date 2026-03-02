<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-gradient-to-b from-slate-50 to-white dark:from-zinc-900 dark:to-zinc-800">
        {{-- Brand header --}}
        <header class="border-b border-zinc-200 dark:border-zinc-700 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-sm">
            <div class="mx-auto max-w-3xl px-6 py-4 flex items-center gap-3">
                <div class="flex size-8 items-center justify-center rounded-md bg-emerald-600 dark:bg-emerald-500 text-white shadow-sm">
                    <x-app-logo-icon class="size-5 fill-current text-white" />
                </div>
                <span class="text-lg font-semibold text-zinc-900 dark:text-zinc-100" style="font-family: var(--font-heading)">Voluntify</span>
            </div>
        </header>

        <div class="mx-auto max-w-3xl px-6 py-8">
            {{ $slot }}
        </div>

        {{-- Footer --}}
        <footer class="border-t border-zinc-200 dark:border-zinc-700 mt-12">
            <div class="mx-auto max-w-3xl px-6 py-6">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">&copy; {{ date('Y') }} Voluntify. {{ __('All rights reserved.') }}</p>
            </div>
        </footer>

        @fluxScripts
    </body>
</html>
