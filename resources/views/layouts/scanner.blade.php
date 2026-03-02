<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <meta name="theme-color" content="#059669">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="manifest" href="/manifest.json">
        @vite('resources/js/scanner.ts')
    </head>
    <body class="min-h-screen bg-zinc-900" data-scanner-layout>
        <div class="flex min-h-screen flex-col">
            {{ $slot }}
        </div>

        @fluxScripts
    </body>
</html>
