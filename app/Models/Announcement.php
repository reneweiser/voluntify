<?php

namespace App\Models;

use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'event_id',
        'job_id',
        'shift_id',
        'subject',
        'body',
        'send_at',
        'sent_at',
        'created_by',
        'recipient_count',
    ];

    protected function casts(): array
    {
        return [
            'send_at' => 'datetime',
            'sent_at' => 'datetime',
            'recipient_count' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(VolunteerJob::class, 'job_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }

    public function isScheduled(): bool
    {
        return $this->send_at !== null && $this->sent_at === null;
    }
}
