# Nexgrow contracts

Hardhat 3 project (ethers + mocha) for the Nexgrow NFT and income system.

## Contracts

| Contract | Role |
|---|---|
| `core/NexgrowNFT` | Sells NFT packages (50–5,000); one active NFT per wallet until its 4X cycle completes |
| `NexgrowManager` | Records sales and keeps the directory of connected modules |
| `core/NexgrowTreasury` | Receives NFT sale payments |
| `core/TeamBusiness` | Team, direct and project business per day (read by business-based modules) |
| `pool/RewardPool`, `pool/LiquidityPool` | Revenue in, per-module budgets, payouts out |
| `income/NFTIncome` | Daily bonus, 4X cap |
| `income/ReferralIncome` | Referral income (unlimited levels) and the sponsor tree |
| `income/TeamBuildingIncome` | Team Building Benefits (20 levels) |
| `income/BonusBooster` | Every 15-Day Bonus Booster |
| `income/TeamPerformanceBonus` | Team targets, 40/20/20/20 legs |
| `income/MonthlyLoyaltyBonus` | 5% monthly pool, equal shares |
| `income/RoyaltyBonus` | 5% global turnover pool for 15 months, equal shares |
| `income/TeamDevelopmentBonus` | Extra % on eligible team bonuses for 12 months |
| `slots/ProfitSharingSlot` | $3,000 Profit-Sharing Slot |

Each contract's header comment states its business rules.

## Test

```shell
npx hardhat test                 # all tests
npx hardhat build && npx tsc --noEmit
npx hardhat run scripts/gasReport.ts
```

## Deploy

```shell
npx hardhat node                                           # local chain
npx hardhat run scripts/deployNexgrow.ts --network localhost
```

`PAYMENT_TOKEN=<address>` uses an existing token; without it a test token is
deployed (local chain only, or with `ALLOW_TEST_TOKEN=1`). Addresses are
written to `deployments/<chainId>.json`.

All payouts stay disabled after deployment. Enabling them needs, per module:
a documented revenue source in the RewardPool, an authorized depositor, an
allocation to the module, then `setPayoutsEnabled(true)`.

## Keeper

```shell
npx hardhat run scripts/keeper.ts --network localhost
```

Does the routine on-chain work (queues, booster and monthly settlement,
performance and development qualification); safe to run repeatedly. Set
`KEEPER_TASKS` to choose tasks (`claims` is opt-in) and `KEEPER_BATCH` for
batch size. It never starts payment rounds: `startRound` is an owner action.

The Laravel app runs it every 10 minutes via `app:run-blockchain-keeper`
once `BLOCKCHAIN_KEEPER_ENABLED=true` (see `laravel/config/blockchain.php`).
