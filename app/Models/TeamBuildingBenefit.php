<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamBuildingBenefit extends Model
{
    protected $fillable = [
        'beneficiary_id',
        'source_user_id',
        'user_nft_id',
        'level',
        'percentage',
        'base_amount',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiary_id');
    }

    public function sourceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }

    public function userNft(): BelongsTo
    {
        return $this->belongsTo(UserNft::class);
    }
}
