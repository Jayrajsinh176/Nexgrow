<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;

/**
 * Recovers the wallet address that signed a message with personal_sign
 * (EIP-191), using ethers via scripts/verify-signature.mjs. Node is already
 * required on the server for the blockchain keeper.
 */
class WalletSignature
{
    /**
     * The checksummed signer address, or null if the signature is invalid.
     */
    public static function recover(string $message, string $signature): ?string
    {
        if (! preg_match('/^0x[0-9a-fA-F]{130}$/', $signature)) {
            return null;
        }

        $env = [
            'SystemRoot' => getenv('SystemRoot') ?: (getenv('SYSTEMROOT') ?: 'C:\\Windows'),
            'WINDIR' => getenv('WINDIR') ?: 'C:\\Windows',
            'PATH' => getenv('PATH') ?: 'C:\\Program Files\\nodejs;C:\\Windows\\System32',
            'LOCALAPPDATA' => getenv('LOCALAPPDATA') ?: '',
            'APPDATA' => getenv('APPDATA') ?: '',
            'USERPROFILE' => getenv('USERPROFILE') ?: '',
            'HOMEDRIVE' => getenv('HOMEDRIVE') ?: 'C:',
            'HOMEPATH' => getenv('HOMEPATH') ?: '\\Users',
            'TEMP' => getenv('TEMP') ?: 'C:\\Windows\\Temp',
            'TMP' => getenv('TMP') ?: 'C:\\Windows\\Temp',
        ];

        $nodeBinary = file_exists('C:\\Program Files\\nodejs\\node.exe')
            ? 'C:\\Program Files\\nodejs\\node.exe'
            : 'node';

        $result = Process::path(base_path())
            ->env($env)
            ->input(json_encode(['message' => $message, 'signature' => $signature]))
            ->timeout(30)
            ->run([$nodeBinary, 'scripts/verify-signature.mjs']);

        if ($result->failed()) {
            \Log::error('Signature recovery process failed', [
                'exit_code' => $result->exitCode(),
                'error_output' => $result->errorOutput(),
                'output' => $result->output(),
            ]);
            return null;
        }

        $address = trim($result->output());

        return preg_match('/^0x[0-9a-fA-F]{40}$/', $address) ? $address : null;
    }

    /**
     * True when the address is a well-formed 0x-prefixed 20-byte address.
     */
    public static function isAddress(string $address): bool
    {
        return (bool) preg_match('/^0x[0-9a-fA-F]{40}$/', $address);
    }
}
