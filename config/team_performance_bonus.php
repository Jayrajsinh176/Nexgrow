<?php

return [
    /*
     * How many of a user's direct-referral legs (ranked by lifetime team
     * business volume, largest first) count toward Team Target
     * qualification. Must match the number of entries in `leg_weights`.
     */
    'qualifying_legs' => 4,

    /*
     * Weight (%) applied to each qualifying leg's business volume, ranked
     * largest to smallest, so no single leg can carry qualification alone.
     * Must sum to 100.
     */
    'leg_weights' => [40, 20, 20, 20],

    /*
     * Lifetime cumulative team-target tiers and their one-time bonus
     * payout. Each tier is awarded at most once per user; keep ascending
     * by target.
     */
    'tiers' => [
        ['target' => 10000, 'bonus' => 500],
        ['target' => 50000, 'bonus' => 2000],
        ['target' => 200000, 'bonus' => 6000],
        ['target' => 1000000, 'bonus' => 20000],
        ['target' => 5000000, 'bonus' => 100000],
        ['target' => 50000000, 'bonus' => 1000000],
        ['target' => 500000000, 'bonus' => 10000000],
    ],
];
