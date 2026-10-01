<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyLoyaltyQualification extends Model
{
    protected $fillable = [
        'user_id',
        'period_start',
        'period_end',
        'direct_business',
        'qualified',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'direct_business' => 'decimal:2',
            'qualified' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
