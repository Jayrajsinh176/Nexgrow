<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonusBooster extends Model
{
    protected $fillable = [
        'user_id',
        'period_start',
        'period_end',
        'business_amount',
        'percentage',
        'bonus_amount',
        'qualified',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'business_amount' => 'decimal:2',
            'percentage' => 'decimal:2',
            'bonus_amount' => 'decimal:2',
            'qualified' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
