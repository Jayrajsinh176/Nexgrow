// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

import "./PayoutRounds.sol";
import "../interfaces/ITeamBusiness.sol";

// Every 15-Day Bonus Booster module.
//
// Business rule (from the project report, exact formula from the Laravel
// backend):
//   A wallet's business for a 15-day period is its team business (own NFT
//   purchases plus its whole downline's) in that period. At the end of the
//   period the wallet earns business * tier%, where the tier is the highest
//   one reached:
//     500 5%, 1,000 7%, 2,500 10%, 5,000 12%, 10,000 15%, 25,000 17%,
//     50,000 20%, 100,000 25%.
//   Every period starts from zero ("refreshes every 15 days").
//
// Periods are shared by all wallets and follow UTC days: period N covers
// days startDay + 15N to startDay + 15N + 14, where startDay is the day this
// contract was deployed.
//
// Team business comes from the TeamBusiness tracker. A period can be settled
// once it has ended and every purchase made in it has been processed there.
// settle() credits the bonus; it is then paid in rounds (see PayoutRounds).
contract BonusBooster is PayoutRounds {

    uint256 public constant PERIOD_DAYS = 15;

    ITeamBusiness public immutable teamBusiness;

    uint256 public immutable startDay;

    // account => period => settled
    mapping(address => mapping(uint256 => bool)) public isSettled;

    event Settled(
        address indexed account,
        uint256 indexed period,
        uint256 business,
        uint256 bps,
        uint256 bonus
    );

    constructor(
        address _manager,
        address _nft,
        address _rewardPool,
        address _teamBusiness
    ) PayoutRounds(_manager, _nft, _rewardPool) {
        require(_teamBusiness != address(0), "Invalid team business");

        teamBusiness = ITeamBusiness(_teamBusiness);
        startDay = block.timestamp / 1 days;
    }

    // ---- Business rules (from the project report, do not change) ----

    function tierBps(uint256 business) public pure returns (uint256) {
        if (business >= 100_000 ether) return 2500;
        if (business >= 50_000 ether) return 2000;
        if (business >= 25_000 ether) return 1700;
        if (business >= 10_000 ether) return 1500;
        if (business >= 5_000 ether) return 1200;
        if (business >= 2_500 ether) return 1000;
        if (business >= 1_000 ether) return 700;
        if (business >= 500 ether) return 500;

        return 0;
    }

    // ---- Periods ----

    function periodOf(uint256 timestamp) public view returns (uint256) {
        uint256 day = timestamp / 1 days;

        require(day >= startDay, "Before start");

        return (day - startDay) / PERIOD_DAYS;
    }

    function currentPeriod() external view returns (uint256) {
        return periodOf(block.timestamp);
    }

    function periodStartDay(uint256 period) public view returns (uint256) {
        return startDay + period * PERIOD_DAYS;
    }

    // First second after the period.
    function periodEnd(uint256 period) public view returns (uint256) {
        return periodStartDay(period + 1) * 1 days;
    }

    function businessOf(address account, uint256 period) public view returns (uint256) {
        uint256 from = periodStartDay(period);

        return teamBusiness.businessBetween(account, from, from + PERIOD_DAYS);
    }

    // True when the period has ended and all its purchases are processed.
    function isSettleable(uint256 period) public view returns (bool) {
        if (block.timestamp < periodEnd(period)) return false;

        return teamBusiness.isProcessedBefore(periodStartDay(period + 1));
    }

    // ---- Settlement ----

    // Credits each account's bonus for a finished period. Anyone can call it.
    // Accounts already settled for the period are skipped.
    function settle(uint256 period, address[] calldata accounts) external {
        require(isSettleable(period), "Period not settleable");

        for (uint256 i = 0; i < accounts.length; i++) {
            address account = accounts[i];

            if (isSettled[account][period]) continue;

            isSettled[account][period] = true;

            uint256 business = businessOf(account, period);
            uint256 bps = tierBps(business);
            uint256 bonus = (business * bps) / BPS_DENOMINATOR;

            if (bonus > 0) {
                _credit(account, bonus);
            }

            emit Settled(account, period, business, bps, bonus);
        }
    }
}
