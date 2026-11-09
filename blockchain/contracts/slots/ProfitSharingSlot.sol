// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/token/ERC20/utils/SafeERC20.sol";
import "@openzeppelin/contracts/utils/Pausable.sol";
import "@openzeppelin/contracts/utils/ReentrancyGuard.sol";
import "../access/NexgrowOwnable.sol";

// $3,000 Profit-Sharing Slot. A separate product from the NFT packages.
//
// Business rules (from the project report and the Laravel backend):
//   - 100 slots in total, 3,000 each, at most one per wallet. Slots belong
//     to the buying wallet and cannot be transferred.
//   - A slot earns until its profit reaches 10X the slot price (30,000) or
//     750 days have passed, whichever comes first (the slot is then matured).
//   - Withdrawing ends the slot and pays principal + profit, minus an early
//     withdrawal deduction based on whole days held:
//       under 180 days 50%, under 360 days 25%, under 540 days 10%.
//     A matured slot has no deduction. Deductions go to the treasury.
//
// Profit (the client's "5% on Nexgrow utility project"):
//   The project's profit is not visible on-chain, so it is deposited:
//   owner-authorized depositors call depositProfit() with the slot holders'
//   share. Each deposit is split equally across slots that are still earning,
//   never beyond a slot's 10X cap; anything that cannot be assigned (no
//   earning slots, or a slot hit its cap) is kept and added to the next
//   deposit (or recovered by the owner). Profit is paid out together with
//   the principal on withdrawal.
//
// Principal stays in this contract until the slot is withdrawn.
// The owner can pause purchases and deposits; withdrawals always stay open.
contract ProfitSharingSlot is NexgrowOwnable, Pausable, ReentrancyGuard {
    using SafeERC20 for IERC20;

    uint256 public constant SLOT_PRICE = 3_000 ether;
    uint256 public constant TOTAL_SLOTS = 100;
    uint256 public constant CAP_MULTIPLIER = 10;
    uint256 public constant PROFIT_CAP = SLOT_PRICE * CAP_MULTIPLIER;
    uint256 public constant MATURITY = 750 days;
    uint256 public constant BPS_DENOMINATOR = 10_000;

    struct Slot {
        address holder;
        uint256 purchasedAt;
        uint256 profit;
        bool withdrawn;
    }

    IERC20 public immutable token;
    address public immutable treasury;

    Slot[] private _slots;

    // wallet => has ever bought a slot
    mapping(address => bool) public hasSlot;

    // wallet => its slot ID
    mapping(address => uint256) public slotOf;

    mapping(address => bool) public isProfitDepositor;

    // Deposited profit not yet assigned to any slot.
    uint256 public undistributedProfit;

    uint256 public totalPrincipal;
    uint256 public totalProfitAssigned;

    event SlotPurchased(uint256 indexed slotId, address indexed holder);
    event ProfitDepositorUpdated(address indexed depositor, bool allowed);
    event ProfitDeposited(address indexed from, uint256 amount, uint256 earningSlots);
    event ProfitAssigned(uint256 indexed slotId, uint256 amount);
    event UndistributedProfitRecovered(address indexed to, uint256 amount);
    event SlotWithdrawn(
        uint256 indexed slotId,
        address indexed holder,
        uint256 total,
        uint256 deductionBps,
        uint256 payout
    );

    constructor(address _token, address _treasury) {
        require(_token != address(0), "Invalid token address");
        require(_treasury != address(0), "Invalid treasury address");

        token = IERC20(_token);
        treasury = _treasury;
    }

    // ---- Business rules (from the project report, do not change) ----

    function deductionBpsFor(uint256 heldDays) public pure returns (uint256) {
        if (heldDays < 180) return 5000;
        if (heldDays < 360) return 2500;
        if (heldDays < 540) return 1000;

        return 0;
    }

    // ---- Administration ----

    function setProfitDepositor(address depositor, bool allowed) external onlyOwner {
        require(depositor != address(0), "Invalid depositor");

        isProfitDepositor[depositor] = allowed;

        emit ProfitDepositorUpdated(depositor, allowed);
    }

    // Returns deposited profit that is not assigned to any slot (e.g. sent
    // when no slot was earning). Assigned profit and principal cannot be
    // touched.
    function recoverUndistributedProfit(address to) external onlyOwner nonReentrant {
        require(to != address(0), "Invalid recipient");

        uint256 amount = undistributedProfit;

        require(amount > 0, "Nothing to recover");

        undistributedProfit = 0;

        token.safeTransfer(to, amount);

        emit UndistributedProfitRecovered(to, amount);
    }

    // Stops purchases and profit deposits. Withdrawals stay open.
    function pause() external onlyOwner {
        _pause();
    }

    function unpause() external onlyOwner {
        _unpause();
    }

    // ---- Views ----

    function slotCount() external view returns (uint256) {
        return _slots.length;
    }

    function slotsRemaining() external view returns (uint256) {
        return TOTAL_SLOTS - _slots.length;
    }

    function getSlot(uint256 slotId) external view returns (Slot memory) {
        require(slotId < _slots.length, "Unknown slot");

        return _slots[slotId];
    }

    function daysHeld(uint256 slotId) public view returns (uint256) {
        return (block.timestamp - _slots[slotId].purchasedAt) / 1 days;
    }

    function isMatured(uint256 slotId) public view returns (bool) {
        Slot storage s = _slots[slotId];

        return
            block.timestamp >= s.purchasedAt + MATURITY ||
            s.profit >= PROFIT_CAP;
    }

    function isEarning(uint256 slotId) public view returns (bool) {
        return !_slots[slotId].withdrawn && !isMatured(slotId);
    }

    // What withdrawing now would pay: (total, deduction in bps, payout).
    function previewWithdraw(uint256 slotId)
        public
        view
        returns (uint256 total, uint256 deductionBps, uint256 payout)
    {
        require(slotId < _slots.length, "Unknown slot");

        Slot storage s = _slots[slotId];

        total = SLOT_PRICE + s.profit;
        deductionBps = isMatured(slotId) ? 0 : deductionBpsFor(daysHeld(slotId));
        payout = total - (total * deductionBps) / BPS_DENOMINATOR;
    }

    // ---- Purchase ----

    function buySlot() external whenNotPaused nonReentrant {
        require(_slots.length < TOTAL_SLOTS, "All slots sold");
        require(!hasSlot[msg.sender], "Already has a slot");

        uint256 balanceBefore = token.balanceOf(address(this));

        token.safeTransferFrom(msg.sender, address(this), SLOT_PRICE);

        require(
            token.balanceOf(address(this)) - balanceBefore == SLOT_PRICE,
            "Payment amount mismatch"
        );

        uint256 slotId = _slots.length;

        _slots.push(Slot({
            holder: msg.sender,
            purchasedAt: block.timestamp,
            profit: 0,
            withdrawn: false
        }));

        hasSlot[msg.sender] = true;
        slotOf[msg.sender] = slotId;
        totalPrincipal += SLOT_PRICE;

        emit SlotPurchased(slotId, msg.sender);
    }

    // ---- Profit ----

    // Splits amount (plus any undistributed profit) equally across earning
    // slots, never beyond a slot's 10X cap. At most 100 slots, so the loop
    // is bounded.
    function depositProfit(uint256 amount) external whenNotPaused nonReentrant {
        require(isProfitDepositor[msg.sender], "Not a profit depositor");
        require(amount > 0, "Invalid amount");

        uint256 balanceBefore = token.balanceOf(address(this));

        token.safeTransferFrom(msg.sender, address(this), amount);

        uint256 received = token.balanceOf(address(this)) - balanceBefore;

        require(received > 0, "Nothing received");

        uint256 available = undistributedProfit + received;
        uint256 count = _slots.length;
        uint256 earning = 0;

        for (uint256 i = 0; i < count; i++) {
            if (isEarning(i)) earning++;
        }

        emit ProfitDeposited(msg.sender, received, earning);

        if (earning > 0) {
            uint256 share = available / earning;

            for (uint256 i = 0; i < count && share > 0; i++) {
                if (!isEarning(i)) continue;

                Slot storage s = _slots[i];

                uint256 room = PROFIT_CAP - s.profit;
                uint256 assigned = share < room ? share : room;

                s.profit += assigned;
                available -= assigned;
                totalProfitAssigned += assigned;

                emit ProfitAssigned(i, assigned);
            }
        }

        undistributedProfit = available;
    }

    // ---- Withdrawal ----

    // Ends the slot and pays principal + profit minus any early-withdrawal
    // deduction, which goes to the treasury.
    function withdraw(uint256 slotId) external nonReentrant {
        require(slotId < _slots.length, "Unknown slot");

        Slot storage s = _slots[slotId];

        require(s.holder == msg.sender, "Not the slot holder");
        require(!s.withdrawn, "Already withdrawn");

        (uint256 total, uint256 deductionBps, uint256 payout) = previewWithdraw(slotId);

        s.withdrawn = true;
        totalPrincipal -= SLOT_PRICE;

        token.safeTransfer(msg.sender, payout);

        if (total > payout) {
            token.safeTransfer(treasury, total - payout);
        }

        emit SlotWithdrawn(slotId, msg.sender, total, deductionBps, payout);
    }
}
