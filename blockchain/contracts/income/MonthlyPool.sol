// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

import "./PayoutRounds.sol";
import "../interfaces/ITeamBusiness.sol";
import "../libraries/CalendarMonths.sol";

// Shared logic for modules that share 5% of the project's monthly business
// (MonthlyLoyaltyBonus, RoyaltyBonus).
//
// Months are UTC calendar months. After a month ends, a 7-day registration
// window opens for wallets that qualified in it; after the window the month
// can be settled. Direct and total business come from the TeamBusiness
// tracker, which records them at purchase time, so a month's figures are
// final when it ends. Credits are paid in rounds (see PayoutRounds).
abstract contract MonthlyPool is PayoutRounds {

    uint256 public constant POOL_BPS = 500;
    uint256 public constant REGISTRATION_DAYS = 7;

    ITeamBusiness public immutable teamBusiness;

    constructor(
        address _manager,
        address _nft,
        address _rewardPool,
        address _teamBusiness
    ) PayoutRounds(_manager, _nft, _rewardPool) {
        require(_teamBusiness != address(0), "Invalid team business");

        teamBusiness = ITeamBusiness(_teamBusiness);
    }

    // ---- Months ----

    function monthOf(uint256 timestamp) public pure returns (uint256) {
        return CalendarMonths.monthOfDay(timestamp / 1 days);
    }

    function currentMonth() external view returns (uint256) {
        return monthOf(block.timestamp);
    }

    function monthStartDay(uint256 month) public pure returns (uint256) {
        return CalendarMonths.startDay(month);
    }

    // First day after the month.
    function monthEndDay(uint256 month) public pure returns (uint256) {
        return CalendarMonths.endDay(month);
    }

    // First day after the registration window.
    function registrationEndDay(uint256 month) public pure returns (uint256) {
        return monthEndDay(month) + REGISTRATION_DAYS;
    }

    // ---- Business ----

    function directBusinessOf(address account, uint256 month) public view returns (uint256) {
        return teamBusiness.directBusinessBetween(
            account,
            monthStartDay(month),
            monthEndDay(month)
        );
    }

    function poolOf(uint256 month) public view returns (uint256) {
        uint256 total = teamBusiness.totalBusinessBetween(
            monthStartDay(month),
            monthEndDay(month)
        );

        return (total * POOL_BPS) / BPS_DENOMINATOR;
    }

    // ---- Windows ----

    function _requireRegistrationOpen(uint256 month) internal view {
        uint256 today = block.timestamp / 1 days;

        require(today >= monthEndDay(month), "Month not ended");
        require(today < registrationEndDay(month), "Registration closed");
    }

    function _requireRegistrationClosed(uint256 month) internal view {
        require(block.timestamp / 1 days >= registrationEndDay(month), "Registration open");
    }
}
