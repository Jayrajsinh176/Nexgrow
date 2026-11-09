// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

import "@openzeppelin/contracts/access/Ownable2Step.sol";

// Ownership used by every Nexgrow contract:
// - Two-step transfer: the new owner must call acceptOwnership(), so a
//   mistyped address cannot take control.
// - Renouncing is disabled, so funds and configuration can never be left
//   without an owner.
abstract contract NexgrowOwnable is Ownable2Step {
    constructor() Ownable(msg.sender) {}

    function renounceOwnership() public view override onlyOwner {
        revert("Renounce disabled");
    }
}
