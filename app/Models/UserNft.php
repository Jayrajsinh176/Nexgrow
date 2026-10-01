<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNft extends Model
{
    protected $fillable = [
        'user_id',
        'nft_package_id',
        'purchase_price',
        'daily_bonus',
        'cap_amount',
        'total_paid_out',
        'status',
        'purchased_at',
        'completed_at',
        'last_paid_at',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'daily_bonus' => 'decimal:2',
            'cap_amount' => 'decimal:2',
            'total_paid_out' => 'decimal:2',
            'purchased_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_paid_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function nftPackage(): BelongsTo
    {
        return $this->belongsTo(NftPackage::class);
    }

    public function remainingCap(): string
    {
        return bcsub($this->cap_amount, $this->total_paid_out, 2);
    }

    public function progressPercent(): float
    {
        if (bccomp($this->cap_amount, '0', 2) === 0) {
            return 0;
        }

        return min(100, (float) bcmul(bcdiv($this->total_paid_out, $this->cap_amount, 4), '100', 2));
    }
}
