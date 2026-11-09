<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * The last blockchain keeper run (scheduled or started from Blockchain Ops),
 * kept in a small JSON file so the admin page can show how it went.
 */
class KeeperStatus
{
    protected const OUTPUT_LIMIT = 6000;

    public static function path(): string
    {
        return config('blockchain.keeper.status_file') ?: storage_path('app/keeper-status.json');
    }

    /**
     * @return array{state: string, tasks: string, trigger: string, started_at: string, finished_at: ?string, output: string}|null
     */
    public static function get(): ?array
    {
        $path = static::path();
        $status = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($status) ? $status : null;
    }

    /**
     * Whether a run is in progress. A run that outlived the keeper timeout is
     * treated as dead (e.g. the process was killed), so a new one may start.
     */
    public static function isRunning(): bool
    {
        $status = static::get();

        if (($status['state'] ?? null) !== 'running') {
            return false;
        }

        $deadline = Carbon::parse($status['started_at'])->addSeconds(config('blockchain.keeper.timeout') + 60);

        return now()->lt($deadline);
    }

    public static function started(string $tasks, string $trigger): void
    {
        static::write([
            'state' => 'running',
            'tasks' => $tasks,
            'trigger' => $trigger,
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'output' => '',
        ]);
    }

    public static function finished(bool $succeeded, string $output): void
    {
        $status = static::get() ?? ['tasks' => '', 'trigger' => '', 'started_at' => now()->toIso8601String()];

        static::write(array_merge($status, [
            'state' => $succeeded ? 'finished' : 'failed',
            'finished_at' => now()->toIso8601String(),
            'output' => mb_substr(trim($output), -self::OUTPUT_LIMIT),
        ]));
    }

    protected static function write(array $status): void
    {
        $path = static::path();

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, json_encode($status, JSON_PRETTY_PRINT), LOCK_EX);
    }
}
