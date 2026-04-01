<x-mail::message>
# Scanner Access: {{ $scannerName }}

You have been assigned as a scanner operator. Click the link below to access the scanner.

**Window:** {{ $startsAt }} &mdash; {{ $endsAt }}

<x-mail::button :url="$url">
Open Scanner
</x-mail::button>

You will need the auth code provided by your organizer to log in.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
