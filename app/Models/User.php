<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'mobile',
        'password',
        'user_id',
        'sponsor_id',
    ];

    protected static function booted(): void
    {
        // The final NEX-prefixed code depends on the auto-increment id, which
        // isn't known until after the row is inserted. So `creating` assigns
        // a temporary unique placeholder, and `created` swaps in the real
        // code once the id exists.
        static::creating(function (User $user) {
            if (empty($user->user_id)) {
                $user->user_id = 'TMP'.Str::random(9);
            }

            if (empty($user->booster_period_started_at)) {
                $user->booster_period_started_at = now();
                $user->next_booster_due_at = now()->addDays(15);
            }
        });

        static::created(function (User $user) {
            if (str_starts_with($user->user_id, 'TMP')) {
                $user->forceFill([
                    'user_id' => static::formatUserId($user->id),
                ])->saveQuietly();
            }
        });
    }

    protected static function formatUserId(int $id): string
    {
        return 'NEX'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }

    /**
     * The user who referred this user (upline).
     */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sponsor_id');
    }

    /**
     * Users directly referred by this user (downline, level 1).
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'sponsor_id');
    }

    /**
     * Total number of users in this user's downline, across all levels.
     */
    public function totalTeamCount(): int
    {
        $count = 0;
        $levelIds = [$this->id];

        while ($levelIds !== []) {
            $levelIds = static::whereIn('sponsor_id', $levelIds)->pluck('id')->all();
            $count += count($levelIds);
        }

        return $count;
    }

    /**
     * Every user in this user's downline, across all levels, each tagged
     * with a team_level attribute (1 = direct referral, 2 = their referrals, ...).
     */
    public function teamMembers(): \Illuminate\Support\Collection
    {
        $members = collect();
        $levelIds = [$this->id];
        $level = 1;

        while ($levelIds !== []) {
            $levelUsers = static::with('sponsor')->whereIn('sponsor_id', $levelIds)->get();

            if ($levelUsers->isEmpty()) {
                break;
            }

            $levelUsers->each->setAttribute('team_level', $level);
            $members = $members->concat($levelUsers);
            $levelIds = $levelUsers->pluck('id')->all();
            $level++;
        }

        return $members;
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)->latest();
    }

    /**
     * Credit tokens to this user's wallet. $performedBy is the admin who
     * initiated it (null for system-generated credits, once those exist).
     */
    public function creditTokens(float $amount, string $reason, ?string $note = null, ?User $performedBy = null): WalletTransaction
    {
        return $this->applyWalletChange('credit', $amount, $reason, $note, $performedBy);
    }

    public function debitTokens(float $amount, string $reason, ?string $note = null, ?User $performedBy = null): WalletTransaction
    {
        return $this->applyWalletChange('debit', $amount, $reason, $note, $performedBy);
    }

    /**
     * Applies a balance change and writes the audit row inside one locked
     * transaction, so concurrent credits/debits can never race each other
     * or leave the ledger out of sync with the cached balance.
     */
    protected function applyWalletChange(string $type, float $amount, string $reason, ?string $note, ?User $performedBy): WalletTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }

        return DB::transaction(function () use ($type, $amount, $reason, $note, $performedBy) {
            $user = static::whereKey($this->id)->lockForUpdate()->firstOrFail();

            if ($type === 'debit' && bccomp($user->token_balance, (string) $amount, 2) < 0) {
                throw new \RuntimeException('Insufficient token balance.');
            }

            $newBalance = $type === 'credit'
                ? bcadd($user->token_balance, (string) $amount, 2)
                : bcsub($user->token_balance, (string) $amount, 2);

            $user->forceFill(['token_balance' => $newBalance])->saveQuietly();
            $this->token_balance = $newBalance;

            return $user->walletTransactions()->create([
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reason' => $reason,
                'note' => $note,
                'created_by' => $performedBy?->id,
            ]);
        });
    }

    public function nfts(): HasMany
    {
        return $this->hasMany(UserNft::class)->latest();
    }

    public function activeNft(): ?UserNft
    {
        return $this->nfts()->where('status', 'active')->first();
    }

    /**
     * Buy an NFT package with tokens. Only one active NFT is allowed at a
     * time; its 4X cap must complete before the next purchase, per the plan.
     */
    public function purchaseNft(NftPackage $package): UserNft
    {
        if ($this->activeNft()) {
            throw new \RuntimeException('You already have an active NFT. Complete its cycle before buying another.');
        }

        return DB::transaction(function () use ($package) {
            $this->debitTokens((float) $package->price, 'nft_purchase', "Purchased {$package->name}");

            return $this->nfts()->create([
                'nft_package_id' => $package->id,
                'purchase_price' => $package->price,
                'daily_bonus' => $package->daily_bonus,
                'cap_amount' => $package->capAmount(),
                'total_paid_out' => 0,
                'status' => 'active',
                'purchased_at' => now(),
            ]);
        });
    }

    public function profitSharingSlots(): HasMany
    {
        return $this->hasMany(ProfitSharingSlot::class)->latest('purchased_at');
    }

    /**
     * Buy a $3,000 Profit-Sharing Slot - a separate product from the NFT
     * packages, capped at a fixed number of slots platform-wide (config
     * `profit_sharing_slot.total_slots`). Limited to one slot per user,
     * ever - already owning one (active or withdrawn) blocks buying
     * another. The 5% profit-sharing rate isn't finalized with the client
     * yet, so the slot is created with zero accrued profit; only the
     * purchase itself (locking the amount) happens here.
     */
    public function purchaseProfitSharingSlot(): ProfitSharingSlot
    {
        if ($this->profitSharingSlots()->exists()) {
            throw new \RuntimeException('You have already purchased a Profit-Sharing Slot.');
        }

        $amount = (string) config('profit_sharing_slot.slot_amount');
        $totalSlots = (int) config('profit_sharing_slot.total_slots');

        return DB::transaction(function () use ($amount, $totalSlots) {
            $sold = ProfitSharingSlot::lockForUpdate()->count();

            if ($sold >= $totalSlots) {
                throw new \RuntimeException('All Profit-Sharing Slots have been sold.');
            }

            $this->debitTokens((float) $amount, 'profit_sharing_slot_purchase', 'Purchased a Profit-Sharing Slot');

            return $this->profitSharingSlots()->create([
                'amount' => $amount,
                'accrued_profit' => 0,
                'status' => 'active',
                'purchased_at' => now(),
            ]);
        });
    }

    /**
     * Withdraw an active Profit-Sharing Slot, applying whatever early-exit
     * penalty the holding period incurs (config
     * `profit_sharing_slot.early_withdrawal_penalties`, calculated on the
     * total of principal + accrued profit). Ends the slot - it can't be
     * withdrawn again.
     */
    public function withdrawProfitSharingSlot(ProfitSharingSlot $slot): ProfitSharingSlot
    {
        if ($slot->user_id !== $this->id) {
            throw new \RuntimeException('This slot does not belong to you.');
        }

        if ($slot->status !== 'active') {
            throw new \RuntimeException('This slot has already been withdrawn.');
        }

        $projection = $slot->projectedWithdrawal();

        return DB::transaction(function () use ($slot, $projection) {
            if (bccomp($projection['payout'], '0', 2) > 0) {
                $this->creditTokens(
                    (float) $projection['payout'],
                    'profit_sharing_slot_withdrawal',
                    "Profit-Sharing Slot withdrawal ({$projection['penalty_percent']}% penalty)"
                );
            }

            $slot->forceFill([
                'status' => 'withdrawn',
                'withdrawn_at' => now(),
                'withdrawal_amount' => $projection['payout'],
                'penalty_percent' => $projection['penalty_percent'],
            ])->save();

            return $slot;
        });
    }

    /**
     * Referral income percentage by upline level, per the plan: 5% (level 1)
     * rising to 10% at level 6, then flat 10% for every level beyond that.
     */
    protected static function referralPercentageForLevel(int $level): string
    {
        return match ($level) {
            1 => '5',
            2 => '6',
            3 => '7',
            4 => '8',
            5 => '9',
            default => '10',
        };
    }

    /**
     * Pay referral income up this user's entire sponsor chain (unlimited
     * levels), each ancestor earning a percentage of the given base amount
     * according to their level distance from this user.
     */
    public function payReferralIncomeUpline(string $baseAmount, UserNft $nft): void
    {
        $level = 1;
        $upline = $this->sponsor;

        while ($upline !== null) {
            $percentage = static::referralPercentageForLevel($level);
            $amount = bcdiv(bcmul($baseAmount, $percentage, 4), '100', 2);

            if (bccomp($amount, '0', 2) > 0) {
                $upline->creditTokens(
                    (float) $amount,
                    'referral_income',
                    "Level {$level} referral income from {$this->name}'s daily bonus"
                );

                $upline->referralIncomesReceived()->create([
                    'source_user_id' => $this->id,
                    'user_nft_id' => $nft->id,
                    'level' => $level,
                    'percentage' => $percentage,
                    'base_amount' => $baseAmount,
                    'amount' => $amount,
                ]);
            }

            $level++;
            $upline = $upline->sponsor;
        }
    }

    public function referralIncomesReceived(): HasMany
    {
        return $this->hasMany(ReferralIncome::class, 'beneficiary_id')->latest();
    }

    public function teamBuildingBenefitsReceived(): HasMany
    {
        return $this->hasMany(TeamBuildingBenefit::class, 'beneficiary_id')->latest();
    }

    /**
     * Team Building Benefit percentage for a given sponsor-chain level,
     * per config `team_building_benefits.tiers`: 10% (level 1), 5% (levels
     * 2-15), 4% (levels 16-20). Returns '0' outside the configured tiers.
     */
    protected static function teamBuildingPercentageForLevel(int $level): string
    {
        foreach (config('team_building_benefits.tiers') as $tier) {
            if ($level >= $tier['from'] && $level <= $tier['to']) {
                return (string) $tier['percentage'];
            }
        }

        return '0';
    }

    /**
     * How many sponsor-chain levels deep this user can currently earn
     * Team Building Benefits from. Requires at least
     * `team_building_benefits.minimum_direct_referrals` direct referrals
     * to activate at all (0 levels below that); once active, each direct
     * referral unlocks one more level, capped at
     * `team_building_benefits.max_levels`.
     */
    public function unlockedTeamBuildingLevels(): int
    {
        $directs = $this->referrals()->count();
        $minimum = (int) config('team_building_benefits.minimum_direct_referrals');
        $maxLevels = (int) config('team_building_benefits.max_levels');

        if ($directs < $minimum) {
            return 0;
        }

        return min($maxLevels, $directs);
    }

    /**
     * Pay Team Building Benefits up this user's sponsor chain, up to
     * config `team_building_benefits.max_levels` deep. A separate,
     * additional module from Referral Income - each ancestor only earns
     * from a level if they personally hold an active NFT (their "self
     * package") AND their own direct-referral count has unlocked that
     * level (see unlockedTeamBuildingLevels()). An ancestor who doesn't
     * qualify for a given level is simply skipped, not a stopping point -
     * later ancestors are checked independently on their own merits.
     */
    public function payTeamBuildingBenefitsUpline(string $baseAmount, UserNft $nft): void
    {
        $maxLevels = (int) config('team_building_benefits.max_levels');
        $level = 1;
        $upline = $this->sponsor;

        while ($upline !== null && $level <= $maxLevels) {
            if ($upline->activeNft() !== null && $level <= $upline->unlockedTeamBuildingLevels()) {
                $percentage = static::teamBuildingPercentageForLevel($level);
                $amount = bcdiv(bcmul($baseAmount, $percentage, 4), '100', 2);

                if (bccomp($amount, '0', 2) > 0) {
                    $upline->creditTokens(
                        (float) $amount,
                        'team_building_benefit',
                        "Level {$level} Team Building Benefit from {$this->name}'s daily bonus"
                    );

                    $upline->teamBuildingBenefitsReceived()->create([
                        'source_user_id' => $this->id,
                        'user_nft_id' => $nft->id,
                        'level' => $level,
                        'percentage' => $percentage,
                        'base_amount' => $baseAmount,
                        'amount' => $amount,
                    ]);
                }
            }

            $level++;
            $upline = $upline->sponsor;
        }
    }

    public function bonusBoosters(): HasMany
    {
        return $this->hasMany(BonusBooster::class)->latest('period_start');
    }

    /**
     * Total NFT purchase volume across this user's whole team (themselves +
     * every level of downline) within a date window - the "business" figure
     * the 15-Day Booster tiers are measured against.
     */
    public function teamBusinessVolume(\Illuminate\Support\Carbon $from, \Illuminate\Support\Carbon $to): string
    {
        $ids = $this->teamMembers()->pluck('id')->push($this->id)->all();

        return (string) UserNft::whereIn('user_id', $ids)
            ->whereBetween('purchased_at', [$from, $to])
            ->sum('purchase_price');
    }

    /**
     * Booster percentage for a given business volume, per the plan's tier
     * table. Returns '0' if below the lowest qualifying threshold.
     */
    public static function boosterPercentageForBusiness(string $business): string
    {
        return match (true) {
            bccomp($business, '100000', 2) >= 0 => '25',
            bccomp($business, '50000', 2) >= 0 => '20',
            bccomp($business, '25000', 2) >= 0 => '17',
            bccomp($business, '10000', 2) >= 0 => '15',
            bccomp($business, '5000', 2) >= 0 => '12',
            bccomp($business, '2500', 2) >= 0 => '10',
            bccomp($business, '1000', 2) >= 0 => '7',
            bccomp($business, '500', 2) >= 0 => '5',
            default => '0',
        };
    }

    /**
     * Close out this user's current 15-day booster window: measure team
     * business, credit the bonus if a tier was reached, then start the next
     * window regardless of the outcome ("refreshes every 15 days").
     */
    public function processBoosterCycle(): BonusBooster
    {
        $periodStart = $this->booster_period_started_at;
        $periodEnd = $this->next_booster_due_at;

        $business = $this->teamBusinessVolume($periodStart, $periodEnd);
        $percentage = static::boosterPercentageForBusiness($business);
        $qualified = bccomp($percentage, '0', 2) > 0;
        $bonusAmount = '0.00';

        if ($qualified) {
            $bonusAmount = bcdiv(bcmul($business, $percentage, 4), '100', 2);

            if (bccomp($bonusAmount, '0', 2) > 0) {
                $this->creditTokens(
                    (float) $bonusAmount,
                    'bonus_booster',
                    "15-Day Booster: {$percentage}% of {$business} team business"
                );
            }
        }

        $record = $this->bonusBoosters()->create([
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'business_amount' => $business,
            'percentage' => $percentage,
            'bonus_amount' => $bonusAmount,
            'qualified' => $qualified,
        ]);

        $this->forceFill([
            'booster_period_started_at' => $periodEnd,
            'next_booster_due_at' => $periodEnd->copy()->addDays(15),
        ])->saveQuietly();

        return $record;
    }

    public function teamPerformanceBonuses(): HasMany
    {
        return $this->hasMany(TeamPerformanceBonus::class)->latest();
    }

    /**
     * This user's direct-referral legs, each tagged with a
     * `business_volume` attribute (their own subtree's lifetime team
     * business), ranked largest first.
     */
    public function legsRankedByBusiness(): \Illuminate\Support\Collection
    {
        return $this->referrals()->get()
            ->map(function (User $leg) {
                $leg->setAttribute(
                    'business_volume',
                    $leg->teamBusinessVolume($leg->created_at, now())
                );

                return $leg;
            })
            ->sortByDesc(fn (User $leg) => (float) $leg->business_volume)
            ->values();
    }

    /**
     * This user's progress toward one specific Team Target tier. Each of
     * the top N legs (config `team_performance_bonus.qualifying_legs`,
     * ranked by volume) must independently reach its own share of the
     * target - its weight (config `team_performance_bonus.leg_weights`)
     * percent of THIS tier's target, e.g. for a 10,000 target and weights
     * 40/20/20/20, the largest leg alone must reach 4,000 and each other
     * qualifying leg must reach 2,000. One leg's surplus never covers
     * another leg's shortfall.
     *
     * @param  array{target: int|string, bonus: int|string}  $tier
     * @return array{target: string, bonus: string, legs: array<int, array>, achieved: bool}
     */
    public function teamPerformanceTierStatus(array $tier): array
    {
        $weights = config('team_performance_bonus.leg_weights');
        $requiredLegs = count($weights);
        $legs = $this->legsRankedByBusiness()->take($requiredLegs);
        $target = (string) $tier['target'];

        $breakdown = [];
        $achieved = true;

        // Always build exactly `$requiredLegs` slots, even if fewer legs
        // exist yet, so the UI can show every required slot (filled or not).
        for ($index = 0; $index < $requiredLegs; $index++) {
            $leg = $legs->get($index);
            $weight = (string) $weights[$index];
            $required = bcdiv(bcmul($target, $weight, 4), '100', 2);
            $volume = $leg?->business_volume ?? '0';
            $met = bccomp($volume, $required, 2) >= 0;

            if (! $met) {
                $achieved = false;
            }

            $breakdown[] = [
                'user_id' => $leg?->id,
                'name' => $leg?->name,
                'weight' => $weight,
                'required' => $required,
                'business_volume' => $volume,
                'met' => $met,
                'progress' => bccomp($required, '0', 2) > 0
                    ? min(100.0, (float) bcdiv(bcmul($volume, '100', 4), $required, 2))
                    : 100.0,
            ];
        }

        return [
            'target' => $target,
            'bonus' => (string) $tier['bonus'],
            'legs' => $breakdown,
            'achieved' => $achieved,
        ];
    }

    /**
     * Award every Team Performance Bonus tier this user's legs newly
     * qualify for (lifetime cumulative), in ascending order. Each tier is
     * paid at most once per user, per the plan; since a higher tier always
     * demands more from every leg, once one tier isn't achieved, none
     * higher can be either.
     *
     * @return \Illuminate\Support\Collection<int, TeamPerformanceBonus>
     */
    public function processTeamPerformanceBonuses(): \Illuminate\Support\Collection
    {
        $awardedTargets = $this->teamPerformanceBonuses()->pluck('team_target');

        $awarded = collect();

        foreach (config('team_performance_bonus.tiers') as $tier) {
            $target = (string) $tier['target'];

            $alreadyAwarded = $awardedTargets->contains(
                fn ($awardedTarget) => bccomp((string) $awardedTarget, $target, 2) === 0
            );

            if ($alreadyAwarded) {
                continue;
            }

            $status = $this->teamPerformanceTierStatus($tier);

            if (! $status['achieved']) {
                break;
            }

            $this->creditTokens(
                (float) $tier['bonus'],
                'team_performance_bonus',
                "Team Performance Bonus: reached {$target} team target"
            );

            $totalVolume = array_reduce(
                $status['legs'],
                fn (string $carry, array $leg) => bcadd($carry, $leg['business_volume'], 2),
                '0'
            );

            $awarded->push($this->teamPerformanceBonuses()->create([
                'team_target' => $target,
                'qualifying_business' => $totalVolume,
                'bonus_amount' => $tier['bonus'],
                'leg_breakdown' => $status['legs'],
            ]));
        }

        return $awarded;
    }

    public function monthlyLoyaltyQualifications(): HasMany
    {
        return $this->hasMany(MonthlyLoyaltyQualification::class)->latest('period_start');
    }

    /**
     * Combined NFT purchase volume of this user's direct (level-1)
     * referrals within a date window - the "Direct Monthly Business"
     * figure Monthly Loyalty Bonus eligibility is measured against. Unlike
     * teamBusinessVolume(), this deliberately excludes deeper levels and
     * the user's own purchases.
     */
    public function directBusinessVolume(\Illuminate\Support\Carbon $from, \Illuminate\Support\Carbon $to): string
    {
        $directIds = $this->referrals()->pluck('id');

        return (string) UserNft::whereIn('user_id', $directIds)
            ->whereBetween('purchased_at', [$from, $to])
            ->sum('purchase_price');
    }

    /**
     * Record this user's Monthly Loyalty Bonus qualification for the given
     * calendar month: whether their direct referrals' combined business
     * reached the threshold (config
     * `monthly_loyalty_bonus.direct_business_threshold`). This only marks
     * eligibility for the company-wide 5% pool - the pool's payout split
     * among qualifiers isn't calculated yet, pending the client's plan.
     */
    public function processMonthlyLoyaltyQualification(\Illuminate\Support\Carbon $periodStart, \Illuminate\Support\Carbon $periodEnd): MonthlyLoyaltyQualification
    {
        $threshold = (string) config('monthly_loyalty_bonus.direct_business_threshold');
        $business = $this->directBusinessVolume($periodStart, $periodEnd);
        $qualified = bccomp($business, $threshold, 2) >= 0;

        return $this->monthlyLoyaltyQualifications()->updateOrCreate(
            ['period_start' => $periodStart->toDateString()],
            [
                'period_end' => $periodEnd,
                'direct_business' => $business,
                'qualified' => $qualified,
            ]
        );
    }

    public function royaltyQualification(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(RoyaltyQualification::class);
    }

    /**
     * Check this user's Royalty Bonus qualification for the given calendar
     * month. Qualification is a one-time lifetime event, per the plan: the
     * first month their direct referrals' combined business reaches the
     * threshold (config `royalty_bonus.direct_business_threshold`) opens a
     * fixed window (config `royalty_bonus.window_months`, e.g. 15 months)
     * during which they're eligible for the Global Turnover Bonus pool.
     * Reaching the threshold again later - whether the window is still
     * open or has expired - does nothing; this is a no-op once a
     * RoyaltyQualification row already exists. The pool's payout split
     * among eligible users isn't finalized with the client yet, so no
     * tokens are credited here.
     */
    public function processRoyaltyQualification(\Illuminate\Support\Carbon $periodStart, \Illuminate\Support\Carbon $periodEnd): ?RoyaltyQualification
    {
        // Query fresh rather than the cached relation property - a stale
        // cached null on this instance (from an earlier check this same
        // request) would otherwise let this slip past and try to insert a
        // second row, violating the one-per-user unique constraint.
        if ($this->royaltyQualification()->exists()) {
            return null;
        }

        $threshold = (string) config('royalty_bonus.direct_business_threshold');
        $business = $this->directBusinessVolume($periodStart, $periodEnd);

        if (bccomp($business, $threshold, 2) < 0) {
            return null;
        }

        $windowMonths = (int) config('royalty_bonus.window_months', 15);

        return $this->royaltyQualification()->create([
            'direct_business' => $business,
            'qualified_at' => $periodStart,
            'window_start' => $periodStart,
            'window_end' => $periodStart->copy()->addMonths($windowMonths),
        ]);
    }

    public function teamDevelopmentQualification(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(TeamDevelopmentQualification::class);
    }

    /**
     * This user's progress toward the Team Development Bonus's one-time
     * qualification: each of the top N teams (config
     * `team_development_bonus.qualifying_teams`, ranked by lifetime team
     * business - same ranking as Team Performance Bonus's legs) must
     * independently reach a flat equal-split requirement (config
     * `team_development_bonus.business_per_team`), unlike Team
     * Performance Bonus's weighted 40/20/20/20 legs. One team's surplus
     * never covers another team's shortfall.
     *
     * @return array{required_per_team: string, teams: array<int, array>, achieved: bool}
     */
    public function teamDevelopmentStatus(): array
    {
        $requiredTeams = (int) config('team_development_bonus.qualifying_teams');
        $required = (string) config('team_development_bonus.business_per_team');
        $teams = $this->legsRankedByBusiness()->take($requiredTeams);

        $breakdown = [];
        $achieved = true;

        // Always build exactly `$requiredTeams` slots, even if fewer teams
        // exist yet, so the UI can show every required slot (filled or not).
        for ($index = 0; $index < $requiredTeams; $index++) {
            $team = $teams->get($index);
            $volume = $team?->business_volume ?? '0';
            $met = bccomp($volume, $required, 2) >= 0;

            if (! $met) {
                $achieved = false;
            }

            $breakdown[] = [
                'user_id' => $team?->id,
                'name' => $team?->name,
                'required' => $required,
                'business_volume' => $volume,
                'met' => $met,
                'progress' => bccomp($required, '0', 2) > 0
                    ? min(100.0, (float) bcdiv(bcmul($volume, '100', 4), $required, 2))
                    : 100.0,
            ];
        }

        return ['required_per_team' => $required, 'teams' => $breakdown, 'achieved' => $achieved];
    }

    /**
     * Check this user's Team Development Bonus qualification. One-time
     * lifetime event, per the plan: the first time all qualifying teams
     * independently reach the per-team business requirement, opens a
     * fixed window (config `team_development_bonus.window_months`, e.g.
     * 12 months). Reaching it again later - window open or expired - does
     * nothing; this is a no-op once a TeamDevelopmentQualification row
     * already exists. The extra-bonus payout mechanics aren't finalized
     * with the client yet, so no tokens are credited here.
     */
    public function processTeamDevelopmentQualification(): ?TeamDevelopmentQualification
    {
        // Query fresh rather than the cached relation property - see the
        // comment in processRoyaltyQualification() for why.
        if ($this->teamDevelopmentQualification()->exists()) {
            return null;
        }

        $status = $this->teamDevelopmentStatus();

        if (! $status['achieved']) {
            return null;
        }

        $windowMonths = (int) config('team_development_bonus.window_months', 12);
        $totalBusiness = array_reduce(
            $status['teams'],
            fn (string $carry, array $team) => bcadd($carry, $team['business_volume'], 2),
            '0'
        );
        $now = now();

        return $this->teamDevelopmentQualification()->create([
            'team_business' => $totalBusiness,
            'team_breakdown' => $status['teams'],
            'qualified_at' => $now,
            'window_start' => $now,
            'window_end' => $now->copy()->addMonths($windowMonths),
        ]);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'token_balance' => 'decimal:2',
            'booster_period_started_at' => 'datetime',
            'next_booster_due_at' => 'datetime',
        ];
    }
}
