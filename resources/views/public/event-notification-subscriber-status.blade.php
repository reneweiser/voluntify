@extends('layouts.public')

@section('content')
    <section class="mx-auto max-w-2xl pt-12">
        <div class="rounded-2xl p-8" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);">
            <p class="text-sm font-semibold uppercase tracking-[0.2em]" style="color: var(--brand);">{{ $title }}</p>
            <h1 class="mt-3 font-bebas text-4xl text-white" style="letter-spacing: 0.04em;">{{ $heading }}</h1>
            <p class="mt-4 text-base" style="color: #d4d4d8;">{{ $message }}</p>

            @if ($actionUrl && $actionLabel)
                <div class="mt-6">
                    <a href="{{ $actionUrl }}" class="public-btn-primary inline-flex items-center justify-center" style="text-decoration: none;">
                        {{ $actionLabel }}
                    </a>
                </div>
            @endif
        </div>
    </section>
@endsection
