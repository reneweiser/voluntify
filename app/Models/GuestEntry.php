<?php

namespace App\Models;

use App\Services\QrCodeGenerator;
use Database\Factories\GuestEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

class GuestEntry extends Model
{
    /** @use HasFactory<GuestEntryFactory> */
    use HasFactory;

    protected $hidden = [
        'qr_token',
    ];

    protected $fillable = [
        'guest_group_id',
        'number',
        'name',
        'email',
        'qr_token',
        'checked_in_at',
        'checked_in_by',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'checked_in_at' => 'datetime',
            'invitation_sent_at' => 'datetime',
            'invitation_queued_at' => 'datetime',
            'invitation_failed_at' => 'datetime',
        ];
    }

    public function scopePendingInvitation(Builder $query): Builder
    {
        return $query
            ->whereNotNull('email')
            ->whereNotNull('qr_token')
            ->whereNull('invitation_sent_at')
            ->whereNull('invitation_queued_at')
            ->whereNull('invitation_failed_at');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(GuestGroup::class, 'guest_group_id');
    }

    public function gear(): HasMany
    {
        return $this->hasMany(GuestEntryGear::class);
    }

    public function checkedInByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function isCheckedIn(): bool
    {
        return $this->checked_in_at !== null;
    }

    public function isInvitationQueued(): bool
    {
        return $this->invitation_queued_at !== null
            && $this->invitation_sent_at === null
            && $this->invitation_failed_at === null;
    }

    public function isInvitationSent(): bool
    {
        return $this->invitation_sent_at !== null;
    }

    public function isInvitationFailed(): bool
    {
        return $this->invitation_failed_at !== null;
    }

    public function isInvitationPending(): bool
    {
        return $this->email !== null
            && $this->qr_token !== null
            && ! $this->isInvitationQueued()
            && ! $this->isInvitationSent()
            && ! $this->isInvitationFailed();
    }

    public function invitationStatus(): string
    {
        if ($this->email === null || $this->qr_token === null) {
            return 'not_ready';
        }

        if ($this->isInvitationFailed()) {
            return 'failed';
        }

        if ($this->isInvitationSent()) {
            return 'sent';
        }

        if ($this->isInvitationQueued()) {
            return 'queued';
        }

        return 'pending';
    }

    /**
     * Display label in "GroupLabel N/Total" format (e.g. "DJ Soundwave 1/3").
     */
    public function displayLabel(): string
    {
        $group = $this->group;

        return "{$group->label} {$this->number}/{$group->guest_count}";
    }

    public function qrCodeSvg(): string
    {
        return app(QrCodeGenerator::class)->generate($this->qr_token);
    }

    public function guestPassUrl(): string
    {
        $this->loadMissing('group.guestList.scanner');

        $expiresAt = $this->group->guestList->scanner?->ends_at?->copy()->addHours(12)
            ?? now()->addDays(7);

        return URL::temporarySignedRoute('guest.pass.show', $expiresAt, ['entry' => $this->id]);
    }
}
