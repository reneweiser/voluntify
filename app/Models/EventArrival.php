<?php

namespace App\Models;

use App\Enums\ArrivalMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventArrival extends Model
{
    /** @use HasFactory<\Database\Factories\EventArrivalFactory> */
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'volunteer_id',
        'event_id',
        'scanned_by',
        'scanned_at',
        'method',
        'flagged',
        'flag_reason',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
            'method' => ArrivalMethod::class,
            'flagged' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(Volunteer::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
