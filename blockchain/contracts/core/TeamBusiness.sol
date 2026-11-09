// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

import "../interfaces/INexgrowManager.sol";
import "../interfaces/ISponsorTree.sol";
import "../interfaces/ITeamBusiness.sol";
import "@openzeppelin/contracts/utils/math/SafeCast.sol";

// Team business tracker shared by the income modules that measure business
// (15-Day Booster, Team Performance, ...).
//
// "Team business" of a wallet = the NFT purchase price paid by the wallet
// itself and its whole downline (unlimited depth), as in the Laravel backend.
// It is kept per UTC day and as a lifetime total.
//
// Also kept per UTC day, recorded immediately at purchase:
// - direct business: purchases made by a wallet's direct referrals
//   (level 1 only, not the wallet itself), counted for the buyer's sponsor
//   at the time of purchase;
// - total business: every purchase in the project.
//
// Gas stays bounded at any depth: each purchase only records a job;
// processQueue() adds it to the buyer and each upline one wallet per step.
// Anyone can call it (e.g. a backend cron). Modules that need complete
// figures for a past window check isProcessedBefore() first.
//
// The sponsor tree is the one kept by ReferralIncome.
contract TeamBusiness is ITeamBusiness {

    // One NFT purchase whose business still has to be added up the chain.
    // Packed into 2 storage slots.
    struct Job {
        address buyer;
        uint96 amount;
        address cursor;
        uint96 day;
    }

    INexgrowManager public immutable manager;
    ISponsorTree public immutable sponsorTree;

    // ---- Job queue ----

    mapping(uint256 => Job) public jobs;

    uint256 public queueHead;
    uint256 public queueTail;

    // ---- Business ----

    mapping(address => uint256) public lifetimeBusiness;

    // account => day => team business (18 decimals)
    mapping(address => mapping(uint256 => uint256)) public businessOnDay;

    // account => day => purchases by its direct referrals
    mapping(address => mapping(uint256 => uint256)) public directBusinessOnDay;

    // day => every purchase in the project
    mapping(uint256 => uint256) public totalBusinessOnDay;

    event PurchaseRecorded(
        uint256 indexed jobId,
        address indexed buyer,
        uint256 amount,
        uint256 day
    );
    event BusinessAdded(
        address indexed account,
        address indexed buyer,
        uint256 indexed day,
        uint256 amount
    );

    constructor(address _manager, address _sponsorTree) {
        require(_manager != address(0), "Invalid manager address");
        require(_sponsorTree != address(0), "Invalid sponsor tree");

        manager = INexgrowManager(_manager);
        sponsorTree = ISponsorTree(_sponsorTree);
    }

    function currentDay() public view returns (uint256) {
        return block.timestamp / 1 days;
    }

    // ---- Recording purchases ----

    // Called by NexgrowManager on every NFT purchase.
    function recordPurchase(address buyer, uint256 amount) external {
        require(msg.sender == address(manager), "Only manager");

        if (amount == 0) return;

        uint256 day = currentDay();

        totalBusinessOnDay[day] += amount;

        address sponsor = sponsorTree.sponsorOf(buyer);

        if (sponsor != address(0)) {
            directBusinessOnDay[sponsor][day] += amount;
        }

        uint256 jobId = queueTail++;

        jobs[jobId] = Job({
            buyer: buyer,
            amount: SafeCast.toUint96(amount),
            cursor: buyer,
            day: SafeCast.toUint96(day)
        });

        emit PurchaseRecorded(jobId, buyer, amount, day);
    }

    // Adds business for up to maxSteps wallets (the buyer, then each upline),
    // oldest purchase first. Returns the number of wallets processed.
    function processQueue(uint256 maxSteps) external returns (uint256 steps) {
        uint256 head = queueHead;
        uint256 tail = queueTail;

        while (steps < maxSteps && head < tail) {
            Job storage j = jobs[head];

            lifetimeBusiness[j.cursor] += j.amount;
            businessOnDay[j.cursor][j.day] += j.amount;

            emit BusinessAdded(j.cursor, j.buyer, j.day, j.amount);

            address next = sponsorTree.sponsorOf(j.cursor);

            if (next == address(0)) {
                delete jobs[head];
                head++;
            } else {
                j.cursor = next;
            }

            steps++;
        }

        queueHead = head;
    }

    function pendingJobs() external view returns (uint256) {
        return queueTail - queueHead;
    }

    // ---- Views ----

    // True when every purchase made before `day` has been processed.
    // Jobs are queued in time order, so it is enough to look at the oldest.
    function isProcessedBefore(uint256 day) external view returns (bool) {
        return queueHead == queueTail || jobs[queueHead].day >= day;
    }

    // Team business from fromDay (inclusive) to toDay (exclusive).
    function businessBetween(
        address account,
        uint256 fromDay,
        uint256 toDay
    ) external view returns (uint256 total) {
        for (uint256 d = fromDay; d < toDay; d++) {
            total += businessOnDay[account][d];
        }
    }

    // Direct business from fromDay (inclusive) to toDay (exclusive).
    function directBusinessBetween(
        address account,
        uint256 fromDay,
        uint256 toDay
    ) external view returns (uint256 total) {
        for (uint256 d = fromDay; d < toDay; d++) {
            total += directBusinessOnDay[account][d];
        }
    }

    // Project-wide business from fromDay (inclusive) to toDay (exclusive).
    function totalBusinessBetween(
        uint256 fromDay,
        uint256 toDay
    ) external view returns (uint256 total) {
        for (uint256 d = fromDay; d < toDay; d++) {
            total += totalBusinessOnDay[d];
        }
    }
}
