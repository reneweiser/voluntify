<?php

namespace App\Models;

use Database\Factories\ProjectScannerAssigneeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectScannerAssignee extends Model
{
    /** @use HasFactory<ProjectScannerAssigneeFactory> */
    use HasFactory;

    protected $fillable = [
        'project_scanner_id',
        'email',
        'link_sent_at',
        'authenticated_at',
    ];

    protected $hidden = [
        'email',
    ];

    protected function casts(): array
    {
        return [
            'link_sent_at' => 'datetime',
            'authenticated_at' => 'datetime',
        ];
    }

    public function projectScanner(): BelongsTo
    {
        return $this->belongsTo(ProjectScanner::class);
    }
}
