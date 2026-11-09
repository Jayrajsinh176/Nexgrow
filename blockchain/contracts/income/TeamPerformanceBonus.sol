// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

import "./PayoutRounds.sol";
import "../interfaces/ISponsorTree.sol";
import "../interfaces/ITeamBusiness.sol";

// Team Performance Bonus module.
//
// Business rule (from the project report, exact formula from the Laravel
// backend):
//   A wallet's legs are its direct referrals; a leg's business is that leg's
//   lifetime team business (its own purchases plus its whole downline's).
//   A team target T is reached when the 4 largest legs each reach their own
//   share of T: the largest 40%, each of the next three 20%. One leg's
//   surplus never covers another leg's shortfall.
//   Each target pays a fixed one-time bonus, in ascending order:
//     10,000 -> 500          50,000 -> 2,000         200,000 -> 6,000
//     1,000,000 -> 20,000    5,000,000 -> 100,000
//     50,000,000 -> 1,000,000                500,000,000 -> 10,000,000
//
// Ranking every leg on-chain is unbounded, so the caller names 4 of the
// wallet's legs instead. If any 4 distinct legs meet "largest >= 40% of T and
// smallest >= 20% of T", the real top 4 do too, so the result is the same as
// ranking all legs.
//
// Business comes from the TeamBusiness tracker. Lifetime business only grows,
// so unprocessed purchases can only delay a target, never award one early.
// qualify() credits the bonus; it is then paid in rounds (see PayoutRounds).
contract TeamPerformanceBonus is PayoutRounds {

    uint256 public constant LEG_COUNT = 4;
    uint256 public constant LEAD_LEG_BPS = 4000;
    uint256 public constant OTHER_LEG_BPS = 2000;
    uint256 public constant TIER_COUNT = 7;

    ISponsorTree public immutable sponsorTree;
    ITeamBusiness public immutable teamBusiness;

    // account => index of the next tier it can reach
    mapping(address => uint256) public nextTier;

    event TargetReached(
        address indexed account,
        uint256 indexed tier,
        uint256 target,
        uint256 bonus
    );

    constructor(
        address _manager,
        address _nft,
        address _rewardPool,
        address _sponsorTree,
        address _teamBusiness
    ) PayoutRounds(_manager, _nft, _rewardPool) {
        require(_sponsorTree != address(0), "Invalid sponsor tree");
        require(_teamBusiness != address(0), "Invalid team business");

        sponsorTree = ISponsorTree(_sponsorTree);
        teamBusiness = ITeamBusiness(_teamBusiness);
    }

    // ---- Business rules (from the project report, do not change) ----

    function tier(uint256 index) public pure returns (uint256 target, uint256 bonus) {
        if (index == 0) return (10_000 ether, 500 ether);
        if (index == 1) return (50_000 ether, 2_000 ether);
        if (index == 2) return (200_000 ether, 6_000 ether);
        if (index == 3) return (1_000_000 ether, 20_000 ether);
        if (index == 4) return (5_000_000 ether, 100_000 ether);
        if (index == 5) return (50_000_000 ether, 1_000_000 ether);
        if (index == 6) return (500_000_000 ether, 10_000_000 ether);

        revert("Invalid tier");
    }

    // True when the largest leg reaches 40% of target and the smallest of
    // the 4 legs reaches 20%.
    function meetsTarget(
        uint256 largest,
        uint256 smallest,
        uint256 target
    ) public pure returns (bool) {
        return
            largest * BPS_DENOMINATOR >= target * LEAD_LEG_BPS &&
            smallest * BPS_DENOMINATOR >= target * OTHER_LEG_BPS;
    }

    // ---- Qualification ----

    // Awards every target the account's legs now reach, in ascending order.
    // legs must be 4 distinct direct referrals of the account. Anyone can
    // call it; the bonus is always credited to the account.
    function qualify(address account, address[LEG_COUNT] calldata legs) external {
        uint256 largest = 0;
        uint256 smallest = type(uint256).max;

        for (uint256 i = 0; i < LEG_COUNT; i++) {
            require(sponsorTree.sponsorOf(legs[i]) == account, "Not a direct leg");

            for (uint256 k = 0; k < i; k++) {
                require(legs[k] != legs[i], "Duplicate leg");
            }

            uint256 business = teamBusiness.lifetimeBusiness(legs[i]);

            if (business > largest) largest = business;
            if (business < smallest) smallest = business;
        }

        uint256 index = nextTier[account];
        uint256 awarded = 0;

        while (index < TIER_COUNT) {
            (uint256 target, uint256 bonus) = tier(index);

            if (!meetsTarget(largest, smallest, target)) break;

            _credit(account, bonus);

            emit TargetReached(account, index, target, bonus);

            index++;
            awarded++;
        }

        require(awarded > 0, "No new target reached");

        nextTier[account] = index;
    }
}
