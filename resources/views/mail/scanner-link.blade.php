<x-mail::message>
# Scanner Access: {{ $scannerName }}

You have been assigned as a scanner operator. Click the link below to access the scanner.

**Window:** {{ $startsAt }} &mdash; {{ $endsAt }}

<x-mail::button :url="$url">
Open Scanner
</x-mail::button>

@if ($authCode)
**Your Auth Code:** `{{ $authCode }}`

Keep this code secure and do not share it publicly.
@else
You will need the auth code provided by your organizer to log in.
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
