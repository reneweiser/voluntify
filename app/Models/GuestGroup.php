<?php

namespace App\Models;

use Database\Factories\GuestGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuestGroup extends Model
{
    /** @use HasFactory<GuestGroupFactory> */
    use HasFactory;

    protected $fillable = [
        'guest_list_id',
        'label',
        'guest_count',
    ];

    protected function casts(): array
    {
        return [
            'guest_count' => 'integer',
        ];
    }

    public function guestList(): BelongsTo
    {
        return $this->belongsTo(GuestList::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(GuestEntry::class);
    }

    public function checkedInCount(): int
    {
        return $this->entries()->whereNotNull('checked_in_at')->count();
    }
}
