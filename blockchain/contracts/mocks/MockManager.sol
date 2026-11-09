// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

import "../interfaces/INexgrowNFT.sol";
import "../interfaces/INFTIncome.sol";

// TEST ONLY. Lets tests call NFTIncome.activateNFT with any package.
contract MockManager {
    INexgrowNFT public nftContract;

    constructor(address _nft) {
        nftContract = INexgrowNFT(_nft);
    }

    function isRegistered(uint256) external pure returns (bool) {
        return true;
    }

    function modules(bytes32) external pure returns (address) {
        return address(0);
    }

    function callActivate(address nftIncome, uint256 tokenId, uint256 packageAmount) external {
        INFTIncome(nftIncome).activateNFT(tokenId, packageAmount);
    }
}
