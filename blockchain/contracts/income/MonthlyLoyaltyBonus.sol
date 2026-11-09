// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

import "./MonthlyPool.sol";

// Monthly Loyalty Bonus module.
//
// Business rule (from the project report and the Laravel backend; the split
// was decided with the client):
//   A wallet qualifies for a UTC calendar month when its direct monthly
//   business (NFT purchases by its direct referrals in that month, not its
//   own and not deeper levels) reaches 3,000.
//   The month's pool is 5% of the entire project's business in that month,
//   shared in equal parts among all wallets that qualified.
//
// The contract cannot list every wallet, so qualifiers are registered:
//   1. After the month ends, a 7-day registration window opens. Anyone
//      (e.g. a backend cron) calls register() for qualifying wallets.
//   2. After the window, settle() credits each registered wallet
//      pool / number of registered wallets.
// Months, pool and payment: see MonthlyPool and PayoutRounds.
contract MonthlyLoyaltyBonus is MonthlyPool {

    uint256 public constant DIRECT_BUSINESS_THRESHOLD = 3_000 ether;

    // month => account => registered as qualifier
    mapping(uint256 => mapping(address => bool)) public isRegistered;

    // month => number of registered qualifiers
    mapping(uint256 => uint256) public qualifierCount;

    // month => account => settled
    mapping(uint256 => mapping(address => bool)) public isSettled;

    event Registered(uint256 indexed month, address indexed account, uint256 directBusiness);
    event Settled(uint256 indexed month, address indexed account, uint256 share);

    constructor(
        address _manager,
        address _nft,
        address _rewardPool,
        address _teamBusiness
    ) MonthlyPool(_manager, _nft, _rewardPool, _teamBusiness) {}

    function shareOf(uint256 month) public view returns (uint256) {
        uint256 count = qualifierCount[month];

        return count == 0 ? 0 : poolOf(month) / count;
    }

    // ---- Registration ----

    // Registers every listed account that qualified for the month. Accounts
    // that did not qualify or are already registered are skipped.
    function register(uint256 month, address[] calldata accounts) external {
        _requireRegistrationOpen(month);

        for (uint256 i = 0; i < accounts.length; i++) {
            address account = accounts[i];

            if (isRegistered[month][account]) continue;

            uint256 business = directBusinessOf(account, month);

            if (business < DIRECT_BUSINESS_THRESHOLD) continue;

            isRegistered[month][account] = true;
            qualifierCount[month] += 1;

            emit Registered(month, account, business);
        }
    }

    // ---- Settlement ----

    // Credits each registered account its equal share. Anyone can call it.
    // Accounts not registered or already settled are skipped.
    function settle(uint256 month, address[] calldata accounts) external {
        _requireRegistrationClosed(month);

        uint256 share = shareOf(month);

        for (uint256 i = 0; i < accounts.length; i++) {
            address account = accounts[i];

            if (!isRegistered[month][account] || isSettled[month][account]) continue;

            isSettled[month][account] = true;

            if (share > 0) {
                _credit(account, share);
            }

            emit Settled(month, account, share);
        }
    }
}
