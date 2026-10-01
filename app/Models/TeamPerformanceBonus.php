<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamPerformanceBonus extends Model
{
    protected $fillable = [
        'user_id',
        'team_target',
        'qualifying_business',
        'bonus_amount',
        'leg_breakdown',
    ];

    protected function casts(): array
    {
        return [
            'team_target' => 'decimal:2',
            'qualifying_business' => 'decimal:2',
            'bonus_amount' => 'decimal:2',
            'leg_breakdown' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
