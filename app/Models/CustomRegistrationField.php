<?php

namespace App\Models;

use App\Enums\CustomFieldType;
use Database\Factories\CustomRegistrationFieldFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomRegistrationField extends Model
{
    /** @use HasFactory<CustomRegistrationFieldFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'event_id',
        'project_id',
        'label',
        'type',
        'options',
        'required',
        'allow_multiple',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => CustomFieldType::class,
            'options' => 'array',
            'required' => 'boolean',
            'allow_multiple' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $field) {
            if (($field->project_id === null) === ($field->event_id === null)) {
                throw new \InvalidArgumentException('Exactly one of project_id or event_id must be set.');
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(CustomFieldResponse::class);
    }

    public function scopeProjectLevel(Builder $query): void
    {
        $query->whereNotNull('project_id');
    }

    public function scopeEventLevel(Builder $query): void
    {
        $query->whereNotNull('event_id');
    }
}
