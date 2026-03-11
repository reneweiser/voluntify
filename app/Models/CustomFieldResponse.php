<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomFieldResponse extends Model
{
    /** @use HasFactory<\Database\Factories\CustomFieldResponseFactory> */
    use HasFactory;

    protected $fillable = [
        'custom_registration_field_id',
        'volunteer_id',
        'value',
    ];

    public function field(): BelongsTo
    {
        return $this->belongsTo(CustomRegistrationField::class, 'custom_registration_field_id');
    }

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(Volunteer::class);
    }
}
