<?php

return [
    /*
     * Fixed price per slot - a separate purchase from the NFT packages,
     * per the plan ("This is NOT the NFT purchase price").
     */
    'slot_amount' => 3000,

    /*
     * Total slots ever mintable across the whole platform. Once this many
     * have been purchased (active or withdrawn), no further purchases are
     * allowed.
     */
    'total_slots' => 100,

    /*
     * A slot stops earning once its total payout reaches this multiple of
     * the slot amount, or `maturity_days` have passed, whichever comes
     * first. The actual 5% profit-sharing earning rate isn't finalized
     * with the client yet, so nothing accrues or is credited here - only
     * the slot itself (purchase, lock, withdrawal) is implemented.
     */
    'cap_multiplier' => 10,
    'maturity_days' => 750,

    /*
     * Early-withdrawal penalty schedule: withdrawing before `before_days`
     * of holding deducts `penalty_percent` from the total payout
     * (principal + any accrued profit). Ordered ascending; the first tier
     * whose `before_days` the holding period is under applies. Holding
     * past the last tier's `before_days` (540) incurs no penalty.
     */
    'early_withdrawal_penalties' => [
        ['before_days' => 180, 'penalty_percent' => 50],
        ['before_days' => 360, 'penalty_percent' => 25],
        ['before_days' => 540, 'penalty_percent' => 10],
    ],
];
