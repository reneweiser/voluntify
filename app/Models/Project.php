<?php

namespace App\Models;

use App\Concerns\HasTitleImage;
use App\ValueObjects\PublicToken;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
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
        static::creating(function (Project $project) {
            if (empty($project->public_token)) {
                $project->public_token = PublicToken::generate()->value;
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(ProjectUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function publishedEvents(): HasMany
    {
        return $this->events()
            ->published()
            ->orderBy('starts_at');
    }

    public function volunteers(): HasMany
    {
        return $this->hasMany(Volunteer::class);
    }

    public function gearItems(): HasMany
    {
        return $this->hasMany(ProjectGearItem::class)->orderBy('sort_order');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function customRegistrationFields(): HasMany
    {
        return $this->hasMany(CustomRegistrationField::class)->orderBy('sort_order');
    }

    public function scanners(): HasMany
    {
        return $this->hasMany(ProjectScanner::class);
    }

    public function guestLists(): HasMany
    {
        return $this->hasMany(GuestList::class);
    }
}
