<?php

namespace App\Models;

use Database\Factories\GuestEntryGearFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestEntryGear extends Model
{
    /** @use HasFactory<GuestEntryGearFactory> */
    use HasFactory;

    protected $table = 'guest_entry_gear';

    protected $fillable = [
        'guest_entry_id',
        'project_gear_item_id',
        'quantity',
        'picked_up_count',
        'selection',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'picked_up_count' => 'integer',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(GuestEntry::class, 'guest_entry_id');
    }

    public function gearItem(): BelongsTo
    {
        return $this->belongsTo(ProjectGearItem::class, 'project_gear_item_id');
    }
}
