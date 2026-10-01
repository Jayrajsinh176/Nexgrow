<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfitSharingSlot extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'accrued_profit',
        'status',
        'purchased_at',
        'withdrawn_at',
        'withdrawal_amount',
        'penalty_percent',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'accrued_profit' => 'decimal:2',
            'purchased_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'withdrawal_amount' => 'decimal:2',
            'penalty_percent' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function daysHeld(): int
    {
        return (int) floor($this->purchased_at->diffInDays(now()));
    }

    public function maturityDate(): \Illuminate\Support\Carbon
    {
        return $this->purchased_at->copy()->addDays((int) config('profit_sharing_slot.maturity_days'));
    }

    public function isMatured(): bool
    {
        return now()->greaterThanOrEqualTo($this->maturityDate());
    }

    /**
     * The early-withdrawal penalty percentage for a given holding period,
     * per config `profit_sharing_slot.early_withdrawal_penalties`. Holding
     * past the schedule's last tier incurs no penalty.
     */
    public static function penaltyPercentForDaysHeld(int $daysHeld): string
    {
        foreach (config('profit_sharing_slot.early_withdrawal_penalties') as $tier) {
            if ($daysHeld < $tier['before_days']) {
                return (string) $tier['penalty_percent'];
            }
        }

        return '0';
    }

    /**
     * What withdrawing right now would pay out: total (principal +
     * accrued profit) minus whatever penalty the current holding period
     * incurs.
     *
     * @return array{penalty_percent: string, payout: string}
     */
    public function projectedWithdrawal(): array
    {
        $penaltyPercent = static::penaltyPercentForDaysHeld($this->daysHeld());
        $total = bcadd($this->amount, $this->accrued_profit, 2);
        $deduction = bcdiv(bcmul($total, $penaltyPercent, 4), '100', 2);

        return [
            'penalty_percent' => $penaltyPercent,
            'payout' => bcsub($total, $deduction, 2),
        ];
    }
}
