// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

import "./BasePool.sol";

// Will fund income modules once a revenue source is defined.
contract RewardPool is BasePool {
    constructor(address _token) BasePool(_token) {}
}
