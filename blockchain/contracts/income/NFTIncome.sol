// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

import "@openzeppelin/contracts/utils/Pausable.sol";
import "@openzeppelin/contracts/utils/ReentrancyGuard.sol";
import "@openzeppelin/contracts/utils/math/SafeCast.sol";
import "../access/NexgrowOwnable.sol";
import "../interfaces/INexgrowManager.sol";
import "../interfaces/INexgrowNFT.sol";
import "../interfaces/INFTIncome.sol";
import "../interfaces/IPoolSpender.sol";
import "../interfaces/IUplineIncome.sol";

// NFT daily bonus module.
//
// Two separate concerns:
// 1. Entitlement (business rule from the project report):
//      entitled = min(4X package, completed days since activation * daily rate)
//    This never changes because of funding.
// 2. Payment (funding rule):
//    Payouts come only from this module's funded budget in the RewardPool.
//    Each round snapshots budget B and total unpaid entitlement T; every NFT
//    claiming in that round receives owed * min(B, T) / T. Whatever is not
//    paid stays owed (carried forward) for later rounds.
//
// Payouts are disabled by default and the pool allocation starts at 0, so no
// tokens move until the funding source and allocation are approved.
// setPayoutsEnabled(true) refuses to run until funding is configured.
// The owner can pause rounds and claims in an emergency; entitlement keeps
// accruing while paused because it is a business rule, not a payment.
//
// Every payout is reported to the ReferralIncome and TeamBuildingIncome
// modules (if connected in the manager) so the earner's uplines are credited
// on what was actually paid.
//
// It also tracks how many active NFTs (4X cycle not completed) each wallet
// owns, for Team Building qualification. Transfers are reported by the NFT
// contract through the manager; syncOwner() repairs any transfer that was
// missed (e.g. while this module was disconnected).
contract NFTIncome is INFTIncome, NexgrowOwnable, Pausable, ReentrancyGuard {

    uint256 public constant CAP_MULTIPLIER = 4;

    bytes32 private constant REFERRAL_INCOME = keccak256("REFERRAL_INCOME");
    bytes32 private constant TEAM_BUILDING_INCOME = keccak256("TEAM_BUILDING_INCOME");

    // Packed into 3 storage slots; values are range-checked when stored.
    struct NFTState {
        bool active;
        uint32 packageAmount;
        uint64 activationDay;
        uint64 lastClaimedRound;
        uint128 dailyRate;
        uint128 cap;
        uint128 paid;
    }

    // Aggregate change applied on the day an NFT reaches its 4X cap.
    // Packed into 2 storage slots; values are range-checked when stored.
    struct CapEvent {
        uint128 count;
        uint128 rate;
        uint128 rateTimesDay;
        uint128 cap;
    }

    struct Round {
        uint256 snapshotDay;
        uint256 budget;
        uint256 totalOwed;
        uint256 paid;
    }

    address public immutable manager;
    INexgrowNFT public immutable nft;
    IPoolSpender public immutable rewardPool;

    bool public payoutsEnabled;

    mapping(uint256 => NFTState) public nfts;

    // ---- Running totals, so total entitlement never needs a loop over NFTs ----
    // For NFTs still below their cap:   sum((day - activationDay) * rate)
    //                                 = day * sumRate - sumRateTimesDay
    // For NFTs that reached their cap: cappedTotal

    uint256 public sumRate;
    uint256 public sumRateTimesDay;
    uint256 public cappedTotal;
    uint256 public totalPaid;

    uint256 public lastProcessedDay;
    uint256 public pendingCapEvents;

    mapping(uint256 => CapEvent) public capEvents;

    // ---- Rounds ----

    uint256 public currentRound;

    mapping(uint256 => Round) public rounds;

    // ---- Active NFT ownership ----

    // NFTs with an uncompleted cycle, per wallet.
    mapping(address => uint256) public activeNFTCount;

    // Wallet each uncompleted NFT is currently counted for (0 when completed).
    mapping(uint256 => address) public countedOwner;

    event NFTActivated(
        uint256 indexed tokenId,
        uint256 packageAmount,
        uint256 dailyRate,
        uint256 cap,
        uint256 activationDay
    );
    event PayoutsEnabledUpdated(bool enabled);
    event RoundStarted(
        uint256 indexed roundId,
        uint256 snapshotDay,
        uint256 budget,
        uint256 totalOwed
    );
    event Claimed(
        uint256 indexed tokenId,
        uint256 indexed roundId,
        address indexed to,
        uint256 owed,
        uint256 paid,
        uint256 carriedForward
    );
    event NFTCycleCompleted(uint256 indexed tokenId);

    modifier onlyManager() {
        require(msg.sender == manager, "Only manager");
        _;
    }

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

        manager = _manager;
        nft = INexgrowNFT(_nft);
        rewardPool = IPoolSpender(_rewardPool);

        lastProcessedDay = currentDay();
    }

    // ---- Business rules (from the project report, do not change) ----

    // Daily bonus per package, in tNEX with 18 decimals (1 tNEX = $1).
    function dailyRateFor(uint256 packageAmount) public pure returns (uint256) {
        if (packageAmount == 50) return 0.30 ether;
        if (packageAmount == 100) return 0.40 ether;
        if (packageAmount == 250) return 0.50 ether;
        if (packageAmount == 500) return 0.60 ether;
        if (packageAmount == 1000) return 0.70 ether;
        if (packageAmount == 2500) return 0.80 ether;
        if (packageAmount == 5000) return 1.00 ether;

        revert("Invalid package");
    }

    function capFor(uint256 packageAmount) public pure returns (uint256) {
        return packageAmount * 1 ether * CAP_MULTIPLIER;
    }

    function currentDay() public view returns (uint256) {
        return block.timestamp / 1 days;
    }

    // ---- Administration ----

    // Enabling requires, in the RewardPool: a documented revenue source
    // (addRevenueSource), an authorized depositor for it (setDepositor),
    // a non-zero allocation to this module (setAllocation), and this module
    // approved as spender. Disabling is always allowed.
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

    // ---- Activation ----

    // Called by NexgrowManager on every NFT purchase.
    function activateNFT(uint256 tokenId, uint256 packageAmount) external onlyManager {
        require(!nfts[tokenId].active, "Already active");
        require(nft.nftPackage(tokenId) == packageAmount, "Package mismatch");

        _activate(tokenId, packageAmount);
    }

    // For NFTs bought before this module was connected to the manager.
    // The package is read from the NFT contract, never supplied by the owner.
    // Activation day is today, so no backdated entitlement is created.
    // NFTs that are already active are skipped.
    function activateExisting(uint256[] calldata tokenIds) external onlyOwner {
        INexgrowManager m = INexgrowManager(manager);

        for (uint256 i = 0; i < tokenIds.length; i++) {
            uint256 tokenId = tokenIds[i];

            require(m.isRegistered(tokenId), "NFT not registered");

            if (nfts[tokenId].active) continue;

            _activate(tokenId, nft.nftPackage(tokenId));
        }
    }

    function _activate(uint256 tokenId, uint256 packageAmount) private {
        uint256 rate = dailyRateFor(packageAmount);
        uint256 cap = capFor(packageAmount);
        uint256 today = currentDay();

        nfts[tokenId] = NFTState({
            active: true,
            packageAmount: SafeCast.toUint32(packageAmount),
            activationDay: SafeCast.toUint64(today),
            lastClaimedRound: 0,
            dailyRate: SafeCast.toUint128(rate),
            cap: SafeCast.toUint128(cap),
            paid: 0
        });

        sumRate += rate;
        sumRateTimesDay += rate * today;

        // Schedule the day this NFT's entitlement reaches 4X.
        uint256 capDay = today + (cap + rate - 1) / rate;

        CapEvent storage e = capEvents[capDay];
        e.count += 1;
        e.rate += SafeCast.toUint128(rate);
        e.rateTimesDay += SafeCast.toUint128(rate * today);
        e.cap += SafeCast.toUint128(cap);

        pendingCapEvents += 1;

        address holder = nft.ownerOf(tokenId);

        countedOwner[tokenId] = holder;
        activeNFTCount[holder] += 1;

        emit NFTActivated(tokenId, packageAmount, rate, cap, today);
    }

    // ---- Active NFT ownership ----

    // Called by NexgrowManager when the NFT contract reports a transfer.
    function onTransfer(uint256 tokenId) external onlyManager {
        _syncOwner(tokenId);
    }

    // Anyone can move an uncompleted NFT's count to its current owner.
    function syncOwner(uint256 tokenId) external {
        _syncOwner(tokenId);
    }

    function hasActiveNFT(address account) external view returns (bool) {
        return activeNFTCount[account] > 0;
    }

    function _syncOwner(uint256 tokenId) private {
        address counted = countedOwner[tokenId];

        if (counted == address(0)) return;

        address holder = nft.ownerOf(tokenId);

        if (holder == counted) return;

        activeNFTCount[counted] -= 1;
        activeNFTCount[holder] += 1;
        countedOwner[tokenId] = holder;
    }

    // ---- Entitlement views ----

    function entitledOf(uint256 tokenId) public view returns (uint256) {
        return _entitledAt(nfts[tokenId], currentDay());
    }

    // Entitlement not yet paid (includes carried-forward amounts).
    function unpaidOf(uint256 tokenId) external view returns (uint256) {
        return entitledOf(tokenId) - nfts[tokenId].paid;
    }

    function isCycleCompleted(uint256 tokenId) external view returns (bool) {
        NFTState storage s = nfts[tokenId];

        return s.active && s.paid == s.cap;
    }

    function totalEntitled() public view returns (uint256) {
        return _totalEntitledAt(currentDay());
    }

    function totalUnpaid() external view returns (uint256) {
        return totalEntitled() - totalPaid;
    }

    // ---- Checkpointing ----

    // Applies scheduled cap events up to today, at most maxDays at a time.
    // Only needed manually if a very long time passed without a round.
    function checkpoint(uint256 maxDays) external {
        _processDays(currentDay(), maxDays);
    }

    // ---- Payment rounds ----

    function startRound() external onlyOwner whenNotPaused {
        require(payoutsEnabled, "Payouts disabled");

        uint256 today = currentDay();

        require(_processDays(today, type(uint256).max), "Checkpoint incomplete");

        uint256 totalOwed = _totalEntitledAt(today) - totalPaid;
        uint256 budget = rewardPool.budgetOf(address(this));

        require(totalOwed > 0, "Nothing owed");
        require(budget > 0, "No funded budget");

        uint256 roundId = ++currentRound;

        rounds[roundId] = Round({
            snapshotDay: today,
            budget: budget,
            totalOwed: totalOwed,
            paid: 0
        });

        emit RoundStarted(roundId, today, budget, totalOwed);
    }

    // Anyone can trigger a claim; payment always goes to the NFT's current owner.
    // NFTs already claimed in this round are skipped.
    function claim(uint256[] calldata tokenIds) external whenNotPaused nonReentrant {
        require(payoutsEnabled, "Payouts disabled");

        uint256 roundId = currentRound;

        require(roundId != 0, "No round started");

        Round storage r = rounds[roundId];

        address referralIncome = INexgrowManager(manager).modules(REFERRAL_INCOME);
        address teamBuildingIncome = INexgrowManager(manager).modules(TEAM_BUILDING_INCOME);

        for (uint256 i = 0; i < tokenIds.length; i++) {
            uint256 tokenId = tokenIds[i];
            NFTState storage s = nfts[tokenId];

            require(s.active, "NFT not active");

            if (s.lastClaimedRound == roundId) continue;

            s.lastClaimedRound = SafeCast.toUint64(roundId);

            uint256 owed = _entitledAt(s, r.snapshotDay) - s.paid;

            if (owed == 0) continue;

            uint256 payout = r.budget >= r.totalOwed
                ? owed
                : (owed * r.budget) / r.totalOwed;

            if (payout == 0) continue;

            s.paid += SafeCast.toUint128(payout);
            totalPaid += payout;
            r.paid += payout;

            address to = nft.ownerOf(tokenId);

            rewardPool.release(to, payout);

            if (referralIncome != address(0)) {
                IUplineIncome(referralIncome).recordPayout(to, payout);
            }

            if (teamBuildingIncome != address(0)) {
                IUplineIncome(teamBuildingIncome).recordPayout(to, payout);
            }

            emit Claimed(tokenId, roundId, to, owed, payout, owed - payout);

            if (s.paid == s.cap) {
                address counted = countedOwner[tokenId];

                activeNFTCount[counted] -= 1;
                countedOwner[tokenId] = address(0);

                emit NFTCycleCompleted(tokenId);
            }
        }
    }

    // ---- Internal ----

    function _entitledAt(NFTState storage s, uint256 day) private view returns (uint256) {
        if (!s.active || day <= s.activationDay) return 0;

        uint256 accrued = (day - s.activationDay) * s.dailyRate;

        return accrued < s.cap ? accrued : uint256(s.cap);
    }

    function _totalEntitledAt(uint256 day) private view returns (uint256) {
        uint256 rate = sumRate;
        uint256 rateTimesDay = sumRateTimesDay;
        uint256 capped = cappedTotal;
        uint256 pending = pendingCapEvents;

        for (uint256 d = lastProcessedDay + 1; d <= day && pending > 0; d++) {
            CapEvent storage e = capEvents[d];

            if (e.count == 0) continue;

            rate -= e.rate;
            rateTimesDay -= e.rateTimesDay;
            capped += e.cap;
            pending -= e.count;
        }

        return day * rate - rateTimesDay + capped;
    }

    // Returns true when fully processed up to toDay.
    function _processDays(uint256 toDay, uint256 maxDays) private returns (bool) {
        uint256 d = lastProcessedDay;
        uint256 steps = 0;

        while (d < toDay && steps < maxDays) {
            if (pendingCapEvents == 0) {
                d = toDay;
                break;
            }

            d++;
            steps++;

            CapEvent storage e = capEvents[d];

            if (e.count == 0) continue;

            sumRate -= e.rate;
            sumRateTimesDay -= e.rateTimesDay;
            cappedTotal += e.cap;
            pendingCapEvents -= e.count;
        }

        lastProcessedDay = d;

        return d >= toDay;
    }
}
