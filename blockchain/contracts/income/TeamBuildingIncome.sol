// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

import "./UplineIncome.sol";
import "../interfaces/INFTIncome.sol";
import "../interfaces/ISponsorTree.sol";

// Team Building Benefits module. Separate from, and paid in addition to,
// referral income.
//
// Business rule (from the project report, exact formula from the Laravel
// backend):
//   Every daily bonus actually paid to a wallet earns its uplines up to 20
//   levels: level 1 10%, levels 2-15 5%, levels 16-20 4%.
//   An upline only earns at a level when, at the time the level is
//   processed:
//   - it owns an active NFT (one whose 4X cycle is not completed), and
//   - it has at least 5 direct referrals, and its direct referral count is
//     at least the level (5 directs unlock levels 1-5, 6 unlock 1-6, ...
//     20 or more unlock all 20).
//   An upline that does not qualify is skipped; the uplines above it are
//   still checked.
//
// The sponsor tree is the one kept by ReferralIncome.
// Queueing, crediting and payment rounds: see UplineIncome.
contract TeamBuildingIncome is UplineIncome {

    uint256 public constant MAX_LEVELS = 20;
    uint256 public constant MIN_DIRECT_REFERRALS = 5;

    ISponsorTree public immutable sponsorTree;

    constructor(
        address _manager,
        address _nft,
        address _rewardPool,
        address _sponsorTree
    ) UplineIncome(_manager, _nft, _rewardPool) {
        require(_sponsorTree != address(0), "Invalid sponsor tree");

        sponsorTree = ISponsorTree(_sponsorTree);
    }

    // ---- Business rules (from the project report, do not change) ----

    function levelBps(uint256 level) public pure returns (uint256) {
        require(level > 0, "Invalid level");

        if (level == 1) return 1000;
        if (level <= 15) return 500;
        if (level <= MAX_LEVELS) return 400;

        return 0;
    }

    // Levels an account can currently earn from: 0 below 5 directs,
    // otherwise min(20, directs).
    function unlockedLevels(address account) public view returns (uint256) {
        uint256 directs = sponsorTree.directCount(account);

        if (directs < MIN_DIRECT_REFERRALS) return 0;

        return directs < MAX_LEVELS ? directs : MAX_LEVELS;
    }

    // ---- Module rules ----

    function sponsorOf(address account) public view override returns (address) {
        return sponsorTree.sponsorOf(account);
    }

    function _shareBps(address beneficiary, uint256 level) internal view override returns (uint256) {
        if (level > unlockedLevels(beneficiary)) return 0;

        address nftIncome = manager.modules(NFT_INCOME);

        if (nftIncome == address(0) || !INFTIncome(nftIncome).hasActiveNFT(beneficiary)) {
            return 0;
        }

        return levelBps(level);
    }

    function _maxLevel() internal pure override returns (uint256) {
        return MAX_LEVELS;
    }
}
