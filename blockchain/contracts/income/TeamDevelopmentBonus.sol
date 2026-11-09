// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

import "./PayoutRounds.sol";
import "../interfaces/ISponsorTree.sol";
import "../interfaces/ITeamBusiness.sol";
import "../interfaces/ITeamDevelopment.sol";
import "../libraries/CalendarMonths.sol";

// Team Development Bonus module.
//
// Business rule (from the project report and the Laravel backend; the extra
// mechanics were decided with the client for the initial implementation):
//   Qualification: 3 distinct direct legs, each with at least 5,000 lifetime
//   team business (the leg's own purchases plus its whole downline's). One
//   leg's surplus never covers another's shortfall. A wallet qualifies once
//   for life; qualifying starts its window (12 calendar months by default).
//   Extra: while the window is open, every bonus the wallet is credited by
//   an eligible module earns an extra share (5% by default), e.g. a 1,000
//   bonus earns 50 extra. Qualifying itself earns nothing.
//
// Configurable by the owner until confirmed with the client:
//   - eligible modules (initially Team Building, 15-Day Booster and Team
//     Performance), setEligibleSource();
//   - extra percentage, setExtraBps(); applies to bonuses credited from then on;
//   - window length in months, setWindowMonths(); applies to wallets that
//     qualify from then on.
//
// Every income module reports each credit here (see PayoutRounds); credits
// from modules that are not eligible are ignored. The extra counts when the
// eligible module credits the bonus. As with Team Performance, the caller
// names 3 legs; if any 3 distinct legs reach 5,000, the real top 3 do too.
// The extra is paid in rounds from this module's own budget (see PayoutRounds).
contract TeamDevelopmentBonus is ITeamDevelopment, PayoutRounds {

    uint256 public constant LEG_COUNT = 3;
    uint256 public constant BUSINESS_PER_LEG = 5_000 ether;
    uint256 public constant MAX_EXTRA_BPS = 10_000;
    uint256 public constant MAX_WINDOW_MONTHS = 120;

    ISponsorTree public immutable sponsorTree;
    ITeamBusiness public immutable teamBusiness;

    // ---- Configuration ----

    uint256 public extraBps = 500;
    uint256 public windowMonths = 12;

    mapping(address => bool) public isEligibleSource;

    // ---- Qualification ----

    mapping(address => bool) public isQualified;

    // account => window [start, end) as timestamps
    mapping(address => uint256) public windowStart;
    mapping(address => uint256) public windowEnd;

    event EligibleSourceUpdated(address indexed module, bool eligible);
    event ExtraBpsUpdated(uint256 bps);
    event WindowMonthsUpdated(uint256 months);
    event Qualified(address indexed account, uint256 windowStart, uint256 windowEnd);
    event ExtraCredited(
        address indexed account,
        address indexed source,
        uint256 bonusAmount,
        uint256 extra
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

    // ---- Configuration ----

    function setEligibleSource(address module, bool eligible) external onlyOwner {
        require(module != address(0), "Invalid module");
        require(module != address(this), "Cannot be eligible itself");

        isEligibleSource[module] = eligible;

        emit EligibleSourceUpdated(module, eligible);
    }

    function setExtraBps(uint256 bps) external onlyOwner {
        require(bps <= MAX_EXTRA_BPS, "Extra too high");

        extraBps = bps;

        emit ExtraBpsUpdated(bps);
    }

    function setWindowMonths(uint256 months) external onlyOwner {
        require(months > 0 && months <= MAX_WINDOW_MONTHS, "Invalid window");

        windowMonths = months;

        emit WindowMonthsUpdated(months);
    }

    // ---- Qualification ----

    // legs must be 3 distinct direct referrals of the account, each with at
    // least 5,000 lifetime team business. Anyone can call it.
    function qualify(address account, address[LEG_COUNT] calldata legs) external {
        require(!isQualified[account], "Already qualified");

        for (uint256 i = 0; i < LEG_COUNT; i++) {
            require(sponsorTree.sponsorOf(legs[i]) == account, "Not a direct leg");

            for (uint256 k = 0; k < i; k++) {
                require(legs[k] != legs[i], "Duplicate leg");
            }

            require(
                teamBusiness.lifetimeBusiness(legs[i]) >= BUSINESS_PER_LEG,
                "Leg below 5,000"
            );
        }

        uint256 start = block.timestamp;
        uint256 endDay = CalendarMonths.addMonths(start / 1 days, windowMonths);
        uint256 end = endDay * 1 days + (start % 1 days);

        isQualified[account] = true;
        windowStart[account] = start;
        windowEnd[account] = end;

        emit Qualified(account, start, end);
    }

    function isInWindow(address account) public view returns (bool) {
        return
            isQualified[account] &&
            block.timestamp >= windowStart[account] &&
            block.timestamp < windowEnd[account];
    }

    // ---- Extra ----

    // Called by every income module when it credits a bonus. Only bonuses
    // from eligible modules, credited while the account's window is open,
    // earn the extra.
    function recordBonus(address account, uint256 amount) external {
        if (!isEligibleSource[msg.sender] || !isInWindow(account)) return;

        uint256 extra = (amount * extraBps) / BPS_DENOMINATOR;

        if (extra == 0) return;

        _credit(account, extra);

        emit ExtraCredited(account, msg.sender, amount, extra);
    }
}
