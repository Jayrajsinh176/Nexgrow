// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

// What NFTIncome calls on every upline income module (referral, team building).
interface IUplineIncome {
    function recordPayout(address earner, uint256 amount) external;
}
