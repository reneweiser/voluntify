@extends('layouts.public')

@section('meta')
    <meta name="robots" content="noindex,nofollow,noarchive">
@endsection

@section('content')
    <div class="mx-auto max-w-2xl pt-10">
        @if ($entry)
            @php($guestList = $entry->group->guestList)

            <div class="mb-8">
                <p class="text-sm uppercase tracking-[0.2em] text-zinc-500">{{ __('Guest Pass') }}</p>
                <h1 class="font-bebas text-4xl tracking-[0.04em] text-white">{{ $guestList->project->name }}</h1>
                <p class="mt-3 text-zinc-400">{{ $guestList->name }}</p>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/5 p-6 shadow-2xl shadow-black/20">
                <div class="mb-6">
                    <p class="text-sm uppercase tracking-[0.2em] text-zinc-500">{{ __('Pass Holder') }}</p>
                    <h2 class="font-bebas text-3xl tracking-[0.04em] text-white">{{ $entry->displayLabel() }}</h2>

                    @if ($entry->name)
                        <p class="mt-2 text-zinc-300">{{ $entry->name }}</p>
                    @endif
                </div>

                <div class="flex justify-center rounded-2xl bg-white p-5">
                    <div class="size-full max-w-80 text-black">
                        {!! $entry->qrCodeSvg() !!}
                    </div>
                </div>

                <p class="mt-6 text-sm text-zinc-300">{{ __('Please present this QR code at the entrance.') }}</p>
            </div>
        @else
            <div class="rounded-2xl border border-amber-400/20 bg-white/5 p-6 shadow-2xl shadow-black/20">
                <p class="text-sm uppercase tracking-[0.2em] text-amber-300">{{ __('Guest Pass Unavailable') }}</p>
                <h1 class="mt-3 font-bebas text-4xl tracking-[0.04em] text-white">{{ __('Link unavailable') }}</h1>
                <p class="mt-4 text-zinc-300">{{ $message }}</p>
                <p class="mt-3 text-sm text-zinc-400">{{ __('Please contact the event organizer if you need a new guest pass email.') }}</p>
            </div>
        @endif
    </div>
@endsection
