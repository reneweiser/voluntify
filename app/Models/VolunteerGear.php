<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerGear extends Model
{
    /** @use HasFactory<\Database\Factories\VolunteerGearFactory> */
    use HasFactory;

    protected $table = 'volunteer_gear';

    protected $fillable = [
        'event_gear_item_id',
        'volunteer_id',
        'size',
        'picked_up_at',
        'picked_up_by',
    ];

    protected function casts(): array
    {
        return [
            'picked_up_at' => 'datetime',
        ];
    }

    public function gearItem(): BelongsTo
    {
        return $this->belongsTo(EventGearItem::class, 'event_gear_item_id');
    }

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(Volunteer::class);
    }

    public function pickedUpBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picked_up_by');
    }
}
