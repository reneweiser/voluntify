<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="bg-background relative flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="absolute inset-x-0 top-0 h-48 bg-gradient-to-b from-emerald-50/50 to-transparent dark:from-emerald-950/20 dark:to-transparent"></div>
            <div class="flex w-full max-w-sm flex-col gap-2">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <span class="flex h-10 w-10 mb-1 items-center justify-center rounded-md bg-emerald-600 dark:bg-emerald-500 text-white shadow-md">
                        <x-app-logo-icon class="size-6 fill-current text-white" />
                    </span>
                    <span class="text-lg font-semibold text-zinc-900 dark:text-zinc-100" style="font-family: var(--font-heading)">{{ config('app.name', 'Voluntify') }}</span>
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
