<?php

namespace App\Models;

use Database\Factories\VolunteerGearFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VolunteerGear extends Model
{
    /** @use HasFactory<VolunteerGearFactory> */
    use HasFactory;

    protected $table = 'volunteer_gear';

    protected $fillable = [
        'project_gear_item_id',
        'volunteer_id',
        'size',
        'quantity_entitled',
    ];

    protected function casts(): array
    {
        return [
            'quantity_entitled' => 'integer',
        ];
    }

    public function gearItem(): BelongsTo
    {
        return $this->belongsTo(ProjectGearItem::class, 'project_gear_item_id');
    }

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(Volunteer::class);
    }

    public function pickups(): HasMany
    {
        return $this->hasMany(VolunteerGearPickup::class);
    }

    public function totalPickedUp(): int
    {
        return (int) ($this->relationLoaded('pickups')
            ? $this->pickups->sum('quantity')
            : $this->pickups()->sum('quantity'));
    }

    public function remainingQuantity(): ?int
    {
        if ($this->quantity_entitled === null) {
            return null;
        }

        return max(0, $this->quantity_entitled - $this->totalPickedUp());
    }

    public function isPickedUp(): bool
    {
        return $this->relationLoaded('pickups')
            ? $this->pickups->isNotEmpty()
            : $this->pickups()->exists();
    }
}
