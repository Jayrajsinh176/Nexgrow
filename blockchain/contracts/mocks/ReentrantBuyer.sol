// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/token/ERC721/IERC721Receiver.sol";

interface INexgrowNFTBuy {
    function buyNFT(uint256 packageAmount) external;
}

// TEST ONLY. Tries to call buyNFT again while its NFT is being minted.
contract ReentrantBuyer is IERC721Receiver {
    INexgrowNFTBuy public immutable nft;
    bool private _attacking;

    constructor(address _nft, address _token) {
        nft = INexgrowNFTBuy(_nft);
        IERC20(_token).approve(_nft, type(uint256).max);
    }

    function attack(uint256 packageAmount) external {
        _attacking = true;
        nft.buyNFT(packageAmount);
        _attacking = false;
    }

    function onERC721Received(
        address,
        address,
        uint256,
        bytes calldata
    ) external returns (bytes4) {
        if (_attacking) {
            _attacking = false;
            nft.buyNFT(50);
        }

        return IERC721Receiver.onERC721Received.selector;
    }
}
