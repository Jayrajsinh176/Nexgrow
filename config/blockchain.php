<?php

return [
    /*
     * Where NFT purchases, slots and income live:
     * - 'database': the original off-chain system (token balance, payout
     *   commands). Nothing on-chain is required.
     * - 'chain': users buy and earn through the Nexgrow contracts with their
     *   own wallet; the database payout commands are switched off so nothing
     *   is paid twice.
     * Wallet linking works in both modes, so users can link ahead of the switch.
     */
    'mode' => env('BLOCKCHAIN_MODE', 'database'),

    /*
     * The network the contracts are deployed on. rpc_url, chain_name and
     * explorer_url let the browser wallet add the network if it is missing.
     */
    'chain' => [
        'id' => (int) env('BLOCKCHAIN_CHAIN_ID', 31337),
        'name' => env('BLOCKCHAIN_CHAIN_NAME', 'Hardhat Local'),
        'rpc_url' => env('BLOCKCHAIN_RPC_URL', 'http://127.0.0.1:8545'),
        'explorer_url' => env('BLOCKCHAIN_EXPLORER_URL'),
        'currency' => env('BLOCKCHAIN_CURRENCY', 'ETH'),
    ],

    /*
     * Contract addresses come from the deployment file written by
     * blockchain/scripts/deployNexgrow.ts; defaults to
     * <blockchain path>/deployments/<chain id>.json.
     */
    'deployment' => env('BLOCKCHAIN_DEPLOYMENT'),

    /*
     * The on-chain keeper (blockchain/scripts/keeper.ts) does the routine
     * contract work the income modules rely on: draining the referral, team
     * building and team business queues, settling booster periods,
     * registering and settling Monthly Loyalty / Royalty, and qualifying
     * Team Performance / Team Development. Every run is safe to repeat.
     *
     * Off by default, so nothing is sent until a network and keeper wallet
     * are configured.
     */
    'keeper' => [
        'enabled' => (bool) env('BLOCKCHAIN_KEEPER_ENABLED', false),

        /*
         * The Hardhat project, and the network name from its
         * hardhat.config.ts. The keeper signs with that network's account.
         */
        'path' => env('BLOCKCHAIN_PATH') ?: base_path('../blockchain'),
        'network' => env('BLOCKCHAIN_NETWORK', 'localhost'),

        /*
         * Queue steps / accounts per transaction.
         */
        'batch' => (int) env('BLOCKCHAIN_KEEPER_BATCH', 100),

        /*
         * Also claim for everyone in payment rounds the contract owner has
         * started. Payment rounds themselves are never started by the keeper.
         */
        'claims' => (bool) env('BLOCKCHAIN_KEEPER_CLAIMS', false),

        'timeout' => (int) env('BLOCKCHAIN_KEEPER_TIMEOUT', 600),
    ],
];
