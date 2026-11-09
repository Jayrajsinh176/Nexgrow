// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

// What every income module calls when it credits a bonus.
interface ITeamDevelopment {
    function recordBonus(address account, uint256 amount) external;
}
