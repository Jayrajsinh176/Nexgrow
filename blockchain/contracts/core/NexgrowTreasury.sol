// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/token/ERC20/utils/SafeERC20.sol";
import "../access/NexgrowOwnable.sol";

// Holds the payment token received from NFT sales.
// Only the owner can move funds out.
contract NexgrowTreasury is NexgrowOwnable {
    using SafeERC20 for IERC20;

    IERC20 public immutable token;

    event Withdrawn(address indexed to, uint256 amount);

    constructor(address _token) {
        require(_token != address(0), "Invalid token address");

        token = IERC20(_token);
    }

    function balance() external view returns (uint256) {
        return token.balanceOf(address(this));
    }

    function withdraw(address to, uint256 amount) external onlyOwner {
        require(to != address(0), "Invalid recipient");
        require(amount > 0, "Invalid amount");

        token.safeTransfer(to, amount);

        emit Withdrawn(to, amount);
    }
}
