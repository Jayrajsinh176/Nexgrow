<?php

return [
    /*
     * How many of a user's direct-referral teams (ranked by lifetime team
     * business volume, largest first) must qualify.
     */
    'qualifying_teams' => 3,

    /*
     * Each qualifying team must independently reach this amount - a flat
     * equal split (15,000 target / 3 teams), unlike Team Performance
     * Bonus's weighted legs. One team's surplus doesn't cover another
     * team's shortfall.
     */
    'business_per_team' => 5000,

    /*
     * Once qualified, a user is eligible for the extra 5% on every team
     * bonus payout for this many months. This is a one-time lifetime
     * qualification - reaching it again later doesn't renew or extend the
     * window. The payout mechanics (which bonus it tops up, and how) are
     * still being finalized with the client, so nothing is credited yet -
     * this only tracks qualification and window status.
     */
    'bonus_percentage' => 5,
    'window_months' => 12,
];
