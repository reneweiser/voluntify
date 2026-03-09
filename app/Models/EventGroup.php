<?php

namespace App\Models;

use App\Concerns\HasTitleImage;
use App\Enums\EventStatus;
use App\ValueObjects\PublicToken;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventGroup extends Model
{
    /** @use HasFactory<\Database\Factories\EventGroupFactory> */
    use HasFactory;

    use HasTitleImage;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'title_image_path',
    ];

    protected static function booted(): void
    {
        static::creating(function (EventGroup $group) {
            if (empty($group->public_token)) {
                $group->public_token = PublicToken::generate()->value;
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function publishedEvents(): HasMany
    {
        return $this->events()
            ->where('status', EventStatus::Published)
            ->orderBy('starts_at');
    }
}
