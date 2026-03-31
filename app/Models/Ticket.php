<?php

namespace App\Models;

use App\Services\QrCodeGenerator;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    protected $fillable = [
        'volunteer_id',
        'project_id',
        'jwt_token',
    ];

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(Volunteer::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function qrCodeSvg(): string
    {
        return app(QrCodeGenerator::class)->generate($this->jwt_token);
    }
}
