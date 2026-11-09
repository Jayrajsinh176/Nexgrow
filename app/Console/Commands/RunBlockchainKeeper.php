<?php

namespace App\Console\Commands;

use App\Support\KeeperStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class RunBlockchainKeeper extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:run-blockchain-keeper
                            {--tasks= : Comma-separated keeper tasks (default: all routine tasks)}
                            {--manual : Started by an admin from Blockchain Ops; runs even while the scheduled keeper is disabled}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the on-chain keeper (blockchain/scripts/keeper.ts): drain the income queues and settle or qualify every module whose work is due.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $config = config('blockchain.keeper');
        $manual = (bool) $this->option('manual');

        if (! $config['enabled'] && ! $manual) {
            $this->info('Blockchain keeper is disabled (BLOCKCHAIN_KEEPER_ENABLED=false).');

            return self::SUCCESS;
        }

        $tasks = $this->option('tasks')
            ?: 'queues,development,performance,booster,monthly'.($config['claims'] ? ',claims' : '');

        $npx = PHP_OS_FAMILY === 'Windows' ? 'npx.cmd' : 'npx';

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
            'KEEPER_TASKS' => $tasks,
            'KEEPER_BATCH' => (string) $config['batch'],
        ];

        KeeperStatus::started($tasks, $manual ? 'manual' : 'scheduled');

        try {
            $result = Process::path($config['path'])
                ->env($env)
                ->timeout($config['timeout'])
                ->run([$npx, 'hardhat', 'run', 'scripts/keeper.ts', '--network', $config['network']]);
        } catch (\Throwable $e) {
            KeeperStatus::finished(false, $e->getMessage());
            Log::error('Blockchain keeper failed', ['network' => $config['network'], 'error' => $e->getMessage()]);
            $this->error('Blockchain keeper failed.');
            $this->line($e->getMessage());

            return self::FAILURE;
        }

        $output = trim($result->output());

        if ($output !== '') {
            $this->line($output);
        }

        if ($result->failed()) {
            $error = trim($result->errorOutput()) ?: $output;

            KeeperStatus::finished(false, trim($output."\n".$error));
            $this->error('Blockchain keeper failed.');
            $this->line($error);
            Log::error('Blockchain keeper failed', ['network' => $config['network'], 'error' => $error]);

            return self::FAILURE;
        }

        KeeperStatus::finished(true, $output ?: 'Work completed.');
        Log::info('Blockchain keeper finished', ['network' => $config['network'], 'tasks' => $tasks]);

        return self::SUCCESS;
    }
}
