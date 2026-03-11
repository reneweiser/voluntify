<?php

namespace App\Models;

use App\Enums\CustomFieldType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomRegistrationField extends Model
{
    /** @use HasFactory<\Database\Factories\CustomRegistrationFieldFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'event_id',
        'label',
        'type',
        'options',
        'required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => CustomFieldType::class,
            'options' => 'array',
            'required' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(CustomFieldResponse::class);
    }
}
