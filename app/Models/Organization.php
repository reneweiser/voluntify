<?php

namespace App\Models;

use App\Enums\SmtpEncryption;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    /** @use HasFactory<\Database\Factories\OrganizationFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'ai_api_key',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption',
        'smtp_from_address',
        'smtp_from_name',
    ];

    /** @var list<string> */
    protected $hidden = [
        'ai_api_key',
        'smtp_password',
    ];

    protected function casts(): array
    {
        return [
            'ai_api_key' => 'encrypted',
            'smtp_password' => 'encrypted',
            'smtp_encryption' => SmtpEncryption::class,
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(OrganizationUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
