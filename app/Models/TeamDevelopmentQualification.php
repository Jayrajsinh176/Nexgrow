<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamDevelopmentQualification extends Model
{
    protected $fillable = [
        'user_id',
        'team_business',
        'team_breakdown',
        'qualified_at',
        'window_start',
        'window_end',
    ];

    protected function casts(): array
    {
        return [
            'team_business' => 'decimal:2',
            'team_breakdown' => 'array',
            'qualified_at' => 'date',
            'window_start' => 'date',
            'window_end' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
