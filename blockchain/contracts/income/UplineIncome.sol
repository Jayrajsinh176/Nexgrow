// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

import "./PayoutRounds.sol";
import "../interfaces/IUplineIncome.sol";
import "@openzeppelin/contracts/utils/math/SafeCast.sol";

// Shared logic for income paid up the sponsor chain on every daily bonus
// payout (ReferralIncome, TeamBuildingIncome).
//
// Gas stays bounded at any depth:
//   NFTIncome only records a job per payout (constant gas). processQueue()
//   walks the sponsor chain one level per step, in batches of any size, and
//   credits each upline its share. Anyone can call it (e.g. a backend cron).
//   Each module decides the share per level and how deep it goes.
//
// Payment rounds: see PayoutRounds.
abstract contract UplineIncome is IUplineIncome, PayoutRounds {

    bytes32 internal constant NFT_INCOME = keccak256("NFT_INCOME");

    // One daily bonus payout whose uplines still have to be credited.
    // Packed into 2 storage slots.
    struct Job {
        address source;
        uint96 amount;
        address cursor;
        uint96 level;
    }

    // ---- Job queue ----

    mapping(uint256 => Job) public jobs;

    uint256 public queueHead;
    uint256 public queueTail;

    event PayoutRecorded(uint256 indexed jobId, address indexed source, uint256 amount);
    event Credited(
        address indexed beneficiary,
        address indexed source,
        uint256 level,
        uint256 baseAmount,
        uint256 amount
    );

    constructor(
        address _manager,
        address _nft,
        address _rewardPool
    ) PayoutRounds(_manager, _nft, _rewardPool) {}

    // ---- Module rules ----

    function sponsorOf(address account) public view virtual returns (address);

    // Share in bps that beneficiary earns at this level. 0 skips it; the walk
    // still continues to the next upline.
    function _shareBps(address beneficiary, uint256 level) internal view virtual returns (uint256);

    // Deepest level this module pays. Unlimited by default.
    function _maxLevel() internal pure virtual returns (uint256) {
        return type(uint256).max;
    }

    // ---- Recording payouts ----

    // Called by NFTIncome for every daily bonus payout.
    function recordPayout(address earner, uint256 amount) external {
        require(msg.sender == manager.modules(NFT_INCOME), "Only NFT income");

        address sponsor = sponsorOf(earner);

        if (sponsor == address(0) || amount == 0) return;

        uint256 jobId = queueTail++;

        jobs[jobId] = Job({
            source: earner,
            amount: SafeCast.toUint96(amount),
            cursor: sponsor,
            level: 1
        });

        emit PayoutRecorded(jobId, earner, amount);
    }

    // Handles up to maxSteps upline levels, oldest job first.
    // Returns the number of levels processed.
    function processQueue(uint256 maxSteps) external returns (uint256 steps) {
        uint256 head = queueHead;
        uint256 tail = queueTail;
        uint256 maxLevel = _maxLevel();

        while (steps < maxSteps && head < tail) {
            Job storage j = jobs[head];

            uint256 credit = (j.amount * _shareBps(j.cursor, j.level)) / BPS_DENOMINATOR;

            if (credit > 0) {
                _credit(j.cursor, credit);

                emit Credited(j.cursor, j.source, j.level, j.amount, credit);
            }

            address next = sponsorOf(j.cursor);

            if (next == address(0) || j.level >= maxLevel) {
                delete jobs[head];
                head++;
            } else {
                j.cursor = next;
                j.level += 1;
            }

            steps++;
        }

        queueHead = head;
    }

    function pendingJobs() external view returns (uint256) {
        return queueTail - queueHead;
    }
}
