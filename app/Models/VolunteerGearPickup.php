<?php

namespace App\Models;

use Database\Factories\VolunteerGearPickupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerGearPickup extends Model
{
    /** @use HasFactory<VolunteerGearPickupFactory> */
    use HasFactory;

    protected $fillable = [
        'volunteer_gear_id',
        'picked_up_by',
        'picked_up_at',
        'state',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'picked_up_at' => 'datetime',
            'quantity' => 'integer',
        ];
    }

    public function volunteerGear(): BelongsTo
    {
        return $this->belongsTo(VolunteerGear::class);
    }

    public function pickedUpBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picked_up_by');
    }
}
