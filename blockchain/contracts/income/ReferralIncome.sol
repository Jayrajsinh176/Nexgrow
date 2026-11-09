// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

import "./UplineIncome.sol";
import "../interfaces/ISponsorTree.sol";

// Referral income module. Also keeps the sponsor tree used by every
// upline income module.
//
// Business rule (from the project report, matches the Laravel backend):
//   Every daily bonus actually paid to a wallet earns its uplines a share of
//   that payout: level 1 5%, 2 6%, 3 7%, 4 8%, 5 9%, level 6 and every level
//   beyond 10%. Depth is unlimited.
//
// Sponsor tree:
//   Each wallet sets its sponsor once with setSponsor(), before its first NFT
//   purchase. The sponsor must own an NFT, and a wallet that already has
//   referrals cannot set a sponsor, so the tree can never contain a loop.
//
// Queueing, crediting and payment rounds: see UplineIncome.
contract ReferralIncome is UplineIncome, ISponsorTree {

    mapping(address => address) private _sponsorOf;
    mapping(address => uint256) public directCount;

    event SponsorSet(address indexed account, address indexed sponsor);

    constructor(
        address _manager,
        address _nft,
        address _rewardPool
    ) UplineIncome(_manager, _nft, _rewardPool) {}

    // ---- Business rules (from the project report, do not change) ----

    function levelBps(uint256 level) public pure returns (uint256) {
        require(level > 0, "Invalid level");

        if (level == 1) return 500;
        if (level == 2) return 600;
        if (level == 3) return 700;
        if (level == 4) return 800;
        if (level == 5) return 900;

        return 1000;
    }

    // ---- Sponsor tree ----

    function sponsorOf(address account)
        public
        view
        override(UplineIncome, ISponsorTree)
        returns (address)
    {
        return _sponsorOf[account];
    }

    // Can be called once, before the wallet buys its first NFT.
    function setSponsor(address sponsor) external {
        require(_sponsorOf[msg.sender] == address(0), "Sponsor already set");
        require(sponsor != address(0), "Invalid sponsor");
        require(sponsor != msg.sender, "Cannot sponsor yourself");
        // A wallet with no referrals cannot be anywhere in the sponsor's
        // upline, so this link cannot close a loop.
        require(directCount[msg.sender] == 0, "Already has referrals");
        require(nft.balanceOf(sponsor) > 0, "Sponsor has no NFT");
        require(!manager.hasPurchased(msg.sender), "Already purchased");

        _sponsorOf[msg.sender] = sponsor;
        directCount[sponsor] += 1;

        emit SponsorSet(msg.sender, sponsor);
    }

    // ---- Internal ----

    function _shareBps(address, uint256 level) internal pure override returns (uint256) {
        return levelBps(level);
    }
}
