<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authorized Admin Wallets
    |--------------------------------------------------------------------------
    |
    | Any wallet address listed here or in ADMIN_WALLETS environment variable
    | can sign in to the administrator console via MetaMask.
    |
    | Example:
    |   'wallets' => [
    |       '0x1234567890abcdef1234567890abcdef12345678',
    |   ],
    |
    */
    'wallets' => array_values(array_filter(array_map(
        fn ($w) => strtolower(trim($w)),
        explode(',', env('ADMIN_WALLETS', ''))
    ))),
];

