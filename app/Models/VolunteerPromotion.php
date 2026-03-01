<?php

namespace App\Models;

use App\Enums\StaffRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerPromotion extends Model
{
    /** @use HasFactory<\Database\Factories\VolunteerPromotionFactory> */
    use HasFactory;

    protected $fillable = [
        'volunteer_id',
        'user_id',
        'promoted_by',
        'role',
        'promoted_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => StaffRole::class,
            'promoted_at' => 'datetime',
        ];
    }

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(Volunteer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function promoter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'promoted_by');
    }
}
