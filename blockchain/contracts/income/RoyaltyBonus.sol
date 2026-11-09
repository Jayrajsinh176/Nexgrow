// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

import "./MonthlyPool.sol";

// Royalty Bonus module (Global Turnover Bonus).
//
// Business rule (from the project report and the Laravel backend; the split
// was decided with the client):
//   The first UTC calendar month in which a wallet's direct monthly business
//   (NFT purchases by its direct referrals in that month) reaches 5,000
//   qualifies it, once for life. It is then eligible for 15 months, starting
//   with the qualifying month. Reaching 5,000 again later does nothing.
//   Each month's pool is 5% of the entire project's business in that month,
//   shared in equal parts among all wallets eligible that month.
//
// Qualification and settlement:
//   1. A wallet can only qualify during the 7-day registration window after
//      the month it reached 5,000, so the window cannot be moved to a later
//      month. Anyone (e.g. a backend cron) calls qualify().
//   2. After a month's registration window, settle() credits each wallet
//      eligible that month pool / number of eligible wallets.
// Months, pool and payment: see MonthlyPool and PayoutRounds.
contract RoyaltyBonus is MonthlyPool {

    uint256 public constant DIRECT_BUSINESS_THRESHOLD = 5_000 ether;
    uint256 public constant WINDOW_MONTHS = 15;

    // account => qualified (once for life)
    mapping(address => bool) public isQualified;

    // account => first month of its eligibility window
    mapping(address => uint256) public windowStart;

    // month => number of eligible wallets
    mapping(uint256 => uint256) public eligibleCount;

    // month => account => settled
    mapping(uint256 => mapping(address => bool)) public isSettled;

    event Qualified(address indexed account, uint256 indexed month, uint256 directBusiness);
    event Settled(uint256 indexed month, address indexed account, uint256 share);

    constructor(
        address _manager,
        address _nft,
        address _rewardPool,
        address _teamBusiness
    ) MonthlyPool(_manager, _nft, _rewardPool, _teamBusiness) {}

    function isEligible(address account, uint256 month) public view returns (bool) {
        if (!isQualified[account]) return false;

        uint256 start = windowStart[account];

        return month >= start && month < start + WINDOW_MONTHS;
    }

    function shareOf(uint256 month) public view returns (uint256) {
        uint256 count = eligibleCount[month];

        return count == 0 ? 0 : poolOf(month) / count;
    }

    // ---- Qualification ----

    // Qualifies every listed account that reached the threshold in the month.
    // Accounts that did not, or already qualified before, are skipped.
    function qualify(uint256 month, address[] calldata accounts) external {
        _requireRegistrationOpen(month);

        for (uint256 i = 0; i < accounts.length; i++) {
            address account = accounts[i];

            if (isQualified[account]) continue;

            uint256 business = directBusinessOf(account, month);

            if (business < DIRECT_BUSINESS_THRESHOLD) continue;

            isQualified[account] = true;
            windowStart[account] = month;

            for (uint256 m = month; m < month + WINDOW_MONTHS; m++) {
                eligibleCount[m] += 1;
            }

            emit Qualified(account, month, business);
        }
    }

    // ---- Settlement ----

    // Credits each account eligible in the month its equal share. Anyone can
    // call it. Accounts not eligible or already settled are skipped.
    function settle(uint256 month, address[] calldata accounts) external {
        _requireRegistrationClosed(month);

        uint256 share = shareOf(month);

        for (uint256 i = 0; i < accounts.length; i++) {
            address account = accounts[i];

            if (!isEligible(account, month) || isSettled[month][account]) continue;

            isSettled[month][account] = true;

            if (share > 0) {
                _credit(account, share);
            }

            emit Settled(month, account, share);
        }
    }
}
