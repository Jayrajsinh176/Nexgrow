// SPDX-License-Identifier: MIT
pragma solidity ^0.8.34;

import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/token/ERC721/IERC721Receiver.sol";

interface INexgrowNFTPurchase {
    function buyNFT(uint256 packageAmount) external;
}

interface IReferralSponsor {
    function setSponsor(address sponsor) external;
}

// TEST ONLY. Tries to set a sponsor from inside its NFT purchase callback.
contract SponsorInCallbackBuyer is IERC721Receiver {
    INexgrowNFTPurchase public immutable nft;
    IReferralSponsor public immutable referral;
    address public immutable sponsor;

    constructor(address _nft, address _token, address _referral, address _sponsor) {
        nft = INexgrowNFTPurchase(_nft);
        referral = IReferralSponsor(_referral);
        sponsor = _sponsor;
        IERC20(_token).approve(_nft, type(uint256).max);
    }

    function buy(uint256 packageAmount) external {
        nft.buyNFT(packageAmount);
    }

    function onERC721Received(
        address,
        address,
        uint256,
        bytes calldata
    ) external returns (bytes4) {
        referral.setSponsor(sponsor);

        return IERC721Receiver.onERC721Received.selector;
    }
}
