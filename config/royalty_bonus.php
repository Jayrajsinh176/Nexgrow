<?php

return [
    /*
     * Direct Business Monthly must reach this amount, in a single calendar
     * month, to qualify for the Royalty Bonus. This is a one-time lifetime
     * qualification - reaching it again later doesn't create, renew, or
     * extend another window.
     */
    'direct_business_threshold' => 5000,

    /*
     * Once qualified, a user is eligible for the 5% Global Turnover Bonus
     * Distribution for this many months. The pool split among eligible
     * users isn't finalized with the client yet, so it isn't calculated or
     * paid out here - this only tracks qualification and window status.
     */
    'pool_percentage' => 5,
    'window_months' => 15,
];
