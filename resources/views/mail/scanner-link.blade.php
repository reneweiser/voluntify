<x-mail::message>
# Scanner Access: {{ $scannerName }}

You have been assigned as a scanner operator. Click the link below to access the scanner.

**Window:** {{ $startsAt }} &mdash; {{ $endsAt }}

<x-mail::button :url="$url">
Open Scanner
</x-mail::button>

@if (strlen($authCode) === 6)
**Your Auth Code:** `{{ $authCode }}`

Keep this code secure and do not share it publicly.
@else
Please ask your organizer to regenerate the auth code for this scanner.
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
