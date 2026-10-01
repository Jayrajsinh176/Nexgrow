<?php

return [
    /*
     * A user's "Direct Monthly Business" - the combined NFT purchase
     * volume of their direct (level-1) referrals within a calendar month -
     * must reach this amount to qualify for that month's distribution.
     */
    'direct_business_threshold' => 3000,

    /*
     * The company-wide pool is 5% of the entire project's business for
     * the month. Reserved for the future payout calculation once the
     * split among qualifiers is finalized with the client - not used yet.
     */
    'pool_percentage' => 5,
];
