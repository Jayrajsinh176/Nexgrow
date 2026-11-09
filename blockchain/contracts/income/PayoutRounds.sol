// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

import "@openzeppelin/contracts/utils/Pausable.sol";
import "@openzeppelin/contracts/utils/ReentrancyGuard.sol";
import "../access/NexgrowOwnable.sol";
import "../interfaces/INexgrowManager.sol";
import "../interfaces/INexgrowNFT.sol";
import "../interfaces/IPoolSpender.sol";
import "../interfaces/ITeamDevelopment.sol";
import "@openzeppelin/contracts/utils/math/SafeCast.sol";

// Shared payment logic for income modules that credit wallets
// (referral, team building, booster, performance, loyalty, royalty,
// team development).
//
// Each module decides when and how much to credit with _credit(). Every
// credit is also reported to the TeamDevelopmentBonus module (if connected
// in the manager), which decides itself whether it earns an extra. Credits are
// paid only from the module's funded budget in the RewardPool, in rounds,
// like NFTIncome: each round snapshots budget B and total unpaid credit T;
// every account claiming in that round receives owed * min(B, T) / T, and the
// rest is carried forward.
// Payouts are disabled by default and setPayoutsEnabled(true) refuses to run
// until funding is configured. Pausing stops rounds and claims; crediting
// keeps working because it is a business rule, not a payment.
abstract contract PayoutRounds is NexgrowOwnable, Pausable, ReentrancyGuard {

    uint256 public constant BPS_DENOMINATOR = 10_000;

    bytes32 internal constant TEAM_DEVELOPMENT = keccak256("TEAM_DEVELOPMENT");

    // Packed into 2 storage slots; values are range-checked when stored.
    struct Earner {
        uint128 credited;
        uint128 paid;
        uint128 creditedAtSnapshot;
        uint64 snapshotRound;
        uint64 lastClaimedRound;
    }

    struct Round {
        uint256 budget;
        uint256 totalOwed;
        uint256 paid;
    }

    INexgrowManager public immutable manager;
    INexgrowNFT public immutable nft;
    IPoolSpender public immutable rewardPool;

    bool public payoutsEnabled;

    // ---- Credits ----

    mapping(address => Earner) public earners;

    uint256 public totalCredited;
    uint256 public totalPaid;

    // ---- Rounds ----

    uint256 public currentRound;

    mapping(uint256 => Round) public rounds;

    event PayoutsEnabledUpdated(bool enabled);
    event RoundStarted(uint256 indexed roundId, uint256 budget, uint256 totalOwed);
    event Claimed(
        address indexed account,
        uint256 indexed roundId,
        uint256 owed,
        uint256 paid,
        uint256 carriedForward
    );

    constructor(
        address _manager,
        address _nft,
        address _rewardPool
    ) {
        require(_manager != address(0), "Invalid manager address");
        require(_nft != address(0), "Invalid NFT address");
        require(_rewardPool != address(0), "Invalid pool address");
        require(
            address(INexgrowManager(_manager).nftContract()) == _nft,
            "NFT does not match manager"
        );

        manager = INexgrowManager(_manager);
        nft = INexgrowNFT(_nft);
        rewardPool = IPoolSpender(_rewardPool);
    }

    // ---- Administration ----

    // Enabling requires, in the RewardPool: a documented revenue source,
    // an authorized depositor for it, a non-zero allocation to this module,
    // and this module approved as spender. Disabling is always allowed.
    function setPayoutsEnabled(bool enabled) external onlyOwner {
        if (enabled) {
            require(
                rewardPool.hasFundingConfigured(address(this)),
                "Funding not configured"
            );
        }

        payoutsEnabled = enabled;

        emit PayoutsEnabledUpdated(enabled);
    }

    function pause() external onlyOwner {
        _pause();
    }

    function unpause() external onlyOwner {
        _unpause();
    }

    // ---- Views ----

    function unpaidOf(address account) external view returns (uint256) {
        Earner storage e = earners[account];

        return e.credited - e.paid;
    }

    function totalUnpaid() external view returns (uint256) {
        return totalCredited - totalPaid;
    }

    // ---- Payment rounds ----

    function startRound() external onlyOwner whenNotPaused {
        require(payoutsEnabled, "Payouts disabled");

        uint256 totalOwed = totalCredited - totalPaid;
        uint256 budget = rewardPool.budgetOf(address(this));

        require(totalOwed > 0, "Nothing owed");
        require(budget > 0, "No funded budget");

        uint256 roundId = ++currentRound;

        rounds[roundId] = Round({
            budget: budget,
            totalOwed: totalOwed,
            paid: 0
        });

        emit RoundStarted(roundId, budget, totalOwed);
    }

    // Anyone can trigger a claim; payment always goes to the account itself.
    // Accounts already claimed in this round are skipped.
    function claim(address[] calldata accounts) external whenNotPaused nonReentrant {
        require(payoutsEnabled, "Payouts disabled");

        uint256 roundId = currentRound;

        require(roundId != 0, "No round started");

        Round storage r = rounds[roundId];

        for (uint256 i = 0; i < accounts.length; i++) {
            address account = accounts[i];
            Earner storage e = earners[account];

            if (e.lastClaimedRound == roundId) continue;

            e.lastClaimedRound = SafeCast.toUint64(roundId);

            // Only credit that existed when the round started counts.
            uint256 creditedAtStart = e.snapshotRound == roundId
                ? e.creditedAtSnapshot
                : e.credited;

            uint256 owed = creditedAtStart - e.paid;

            if (owed == 0) continue;

            uint256 payout = r.budget >= r.totalOwed
                ? owed
                : (owed * r.budget) / r.totalOwed;

            if (payout == 0) continue;

            e.paid += SafeCast.toUint128(payout);
            totalPaid += payout;
            r.paid += payout;

            rewardPool.release(account, payout);

            emit Claimed(account, roundId, owed, payout, owed - payout);
        }
    }

    // ---- Internal ----

    function _credit(address account, uint256 amount) internal {
        Earner storage e = earners[account];

        // Remember the credit as of the start of the current round, so later
        // credits are paid in the next round, not this one.
        if (e.snapshotRound != currentRound) {
            e.snapshotRound = SafeCast.toUint64(currentRound);
            e.creditedAtSnapshot = e.credited;
        }

        e.credited += SafeCast.toUint128(amount);
        totalCredited += amount;

        address teamDevelopment = manager.modules(TEAM_DEVELOPMENT);

        if (teamDevelopment != address(0) && teamDevelopment != address(this)) {
            ITeamDevelopment(teamDevelopment).recordBonus(account, amount);
        }
    }
}
