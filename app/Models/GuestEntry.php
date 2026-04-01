<?php

namespace App\Models;

use App\Services\QrCodeGenerator;
use Database\Factories\GuestEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuestEntry extends Model
{
    /** @use HasFactory<GuestEntryFactory> */
    use HasFactory;

    protected $hidden = [
        'qr_token',
    ];

    protected $fillable = [
        'guest_group_id',
        'number',
        'name',
        'email',
        'qr_token',
        'checked_in_at',
        'checked_in_by',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'checked_in_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(GuestGroup::class, 'guest_group_id');
    }

    public function gear(): HasMany
    {
        return $this->hasMany(GuestEntryGear::class);
    }

    public function checkedInByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function isCheckedIn(): bool
    {
        return $this->checked_in_at !== null;
    }

    /**
     * Display label in "GroupLabel N/Total" format (e.g. "DJ Soundwave 1/3").
     */
    public function displayLabel(): string
    {
        $group = $this->group;

        return "{$group->label} {$this->number}/{$group->guest_count}";
    }

    public function qrCodeSvg(): string
    {
        return app(QrCodeGenerator::class)->generate($this->qr_token);
    }
}
