<?php

namespace App\Console\Commands;

use App\Support\Chain;

/**
 * For the database payout commands: in chain mode income is paid by the
 * Nexgrow contracts, so these must not credit anything (no double payouts).
 */
trait SkipsInChainMode
{
    protected function skippedInChainMode(): bool
    {
        if (! Chain::enabled()) {
            return false;
        }

        $this->info('Skipped: income is paid on-chain (BLOCKCHAIN_MODE=chain).');

        return true;
    }
}
