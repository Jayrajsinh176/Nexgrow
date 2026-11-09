// Prints the wallet address that signed a message (EIP-191 personal_sign).
// Input on stdin: {"message": "...", "signature": "0x..."}
// Output: the checksummed address, or exit code 1 if the input is invalid.
import { verifyMessage } from 'ethers';

let input = '';

process.stdin.setEncoding('utf8');
process.stdin.on('data', (chunk) => { input += chunk; });
process.stdin.on('end', () => {
    try {
        const { message, signature } = JSON.parse(input);

        process.stdout.write(verifyMessage(message, signature));
    } catch (error) {
        process.stderr.write(String(error?.message ?? error));
        process.exit(1);
    }
});
