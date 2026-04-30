<?php

namespace App\Http\Controllers;

use App\Models\GuestEntry;
use Illuminate\Http\Response;

class GuestPassController extends Controller
{
    public function __invoke(GuestEntry $entry): Response
    {
        $entry->loadMissing(['group.guestList.project', 'group.guestList.scanner']);

        abort_if(blank($entry->qr_token), 404);
        abort_if(! $entry->group->guestList->isConfirmed(), 404);

        return response()->view('public.guest-pass', [
            'entry' => $entry,
            'message' => null,
            'title' => $entry->displayLabel().' - Guest Pass',
        ], 200, [
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }
}
