<?php

return [
    /*
     * Maximum sponsor-chain depth this benefit pays to, unlike Referral
     * Income's unlimited levels.
     */
    'max_levels' => 20,

    /*
     * An upline member must have at least this many direct referrals
     * before the benefit activates for them at all. Below this, they earn
     * nothing from any level.
     */
    'minimum_direct_referrals' => 5,

    /*
     * Once active, an upline member's own direct-referral count decides
     * how many levels deep they can earn from: min(max_levels, their
     * direct referral count) - e.g. exactly 5 directs unlocks levels
     * 1-5, 6 directs unlocks 1-6, ... 20+ directs unlocks all 20.
     */

    /*
     * Percentage of the triggering daily bonus paid per level, by range.
     */
    'tiers' => [
        ['from' => 1, 'to' => 1, 'percentage' => 10],
        ['from' => 2, 'to' => 15, 'percentage' => 5],
        ['from' => 16, 'to' => 20, 'percentage' => 4],
    ],
];
