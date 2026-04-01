<?php

namespace App\Models;

use Database\Factories\AnnouncementTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementTemplate extends Model
{
    /** @use HasFactory<AnnouncementTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'subject',
        'body',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
