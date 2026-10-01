<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoyaltyQualification extends Model
{
    protected $fillable = [
        'user_id',
        'direct_business',
        'qualified_at',
        'window_start',
        'window_end',
    ];

    protected function casts(): array
    {
        return [
            'direct_business' => 'decimal:2',
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
