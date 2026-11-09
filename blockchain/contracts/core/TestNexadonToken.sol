// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

import "@openzeppelin/contracts/token/ERC20/ERC20.sol";

contract TestNexadonToken is ERC20 {

    constructor() ERC20("Test Nexadon Token", "tNEX") {
        _mint(msg.sender, 1_000_000 * 10 ** decimals());
    }
}