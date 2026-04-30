<x-mail::message>
# Your Guest Pass — {{ $guestListName }}

You have been invited as a guest to **{{ $projectName }}**.

@foreach ($entries as $entry)
---

**{{ $entry->displayLabel() }}**
@if ($entry->name)
Name: {{ $entry->name }}
@endif

{!! $entry->qrCodeSvg() !!}

If the QR code is not visible in your email app, open this pass in your browser.

<a href="{{ $entry->guestPassUrl() }}">{{ $entry->guestPassUrl() }}</a>

@endforeach

Please present your QR code at the entrance.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
