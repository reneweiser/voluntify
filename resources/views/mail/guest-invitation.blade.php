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

@endforeach

Please present your QR code at the entrance.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
