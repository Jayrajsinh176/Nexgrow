<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\KeeperStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class RunBlockchainKeeperTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['blockchain.keeper.status_file' => sys_get_temp_dir().'/nexgrow-keeper-status-'.getmypid().'.json']);
        @unlink(KeeperStatus::path());
    }

    protected function tearDown(): void
    {
        @unlink(KeeperStatus::path());

        parent::tearDown();
    }

    public function test_a_manual_run_works_while_the_schedule_is_disabled_and_records_its_status(): void
    {
        Process::fake(['*' => Process::result('[keeper] queues drained')]);
        config(['blockchain.keeper.enabled' => false]);

        $this->artisan('app:run-blockchain-keeper', ['--manual' => true, '--tasks' => 'queues'])->assertSuccessful();

        Process::assertRan(fn (PendingProcess $p) => $p->environment['KEEPER_TASKS'] === 'queues');

        $status = KeeperStatus::get();
        $this->assertSame('finished', $status['state']);
        $this->assertSame('manual', $status['trigger']);
        $this->assertSame('[keeper] queues drained', $status['output']);
        $this->assertNotNull($status['finished_at']);
    }

    public function test_a_failed_run_is_recorded_with_its_error(): void
    {
        Process::fake(['*' => Process::result(output: '', errorOutput: 'insufficient funds for gas', exitCode: 1)]);
        config(['blockchain.keeper.enabled' => true]);

        $this->artisan('app:run-blockchain-keeper')->assertFailed();

        $this->assertSame('failed', KeeperStatus::get()['state']);
        $this->assertStringContainsString('insufficient funds for gas', KeeperStatus::get()['output']);
        $this->assertFalse(KeeperStatus::isRunning());
    }

    public function test_the_button_starts_the_keeper_in_the_background(): void
    {
        Process::fake();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->from(route('admin.blockchain.index'))
            ->post(route('admin.blockchain.keeper'), ['tasks' => 'queues'])
            ->assertRedirect(route('admin.blockchain.index'))
            ->assertSessionHas('status');

        Process::assertRan(function (PendingProcess $p) {
            $command = (string) $p->command;

            return str_contains($command, 'app:run-blockchain-keeper --manual --tasks=')
                && str_contains($command, 'queues')
                && (str_starts_with($command, 'start "" /B ') || str_starts_with($command, 'nohup '));
        });

        $this->assertTrue(KeeperStatus::isRunning());

        // A second click while it runs is refused.
        $this->actingAs($admin)
            ->from(route('admin.blockchain.index'))
            ->post(route('admin.blockchain.keeper'), ['tasks' => 'queues'])
            ->assertSessionHasErrors('keeper');

        Process::assertRanTimes(fn () => true, 1);

        $this->actingAs($admin)->get(route('admin.blockchain.index'))
            ->assertOk()
            ->assertSee('Running…');
    }

    public function test_the_button_only_accepts_listed_tasks(): void
    {
        Process::fake();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.blockchain.keeper'), ['tasks' => 'queues & del *'])
            ->assertSessionHasErrors('tasks');

        Process::assertNothingRan();
    }

    public function test_a_run_older_than_the_timeout_no_longer_counts_as_running(): void
    {
        KeeperStatus::started('queues', 'manual');
        $this->assertTrue(KeeperStatus::isRunning());

        $this->travel(config('blockchain.keeper.timeout') + 61)->seconds();

        $this->assertFalse(KeeperStatus::isRunning());
    }

    public function test_it_does_nothing_while_disabled(): void
    {
        Process::fake();
        config(['blockchain.keeper.enabled' => false]);

        $this->artisan('app:run-blockchain-keeper')
            ->expectsOutputToContain('disabled')
            ->assertSuccessful();

        Process::assertNothingRan();
    }

    public function test_it_runs_the_keeper_script_on_the_configured_network(): void
    {
        Process::fake(['*' => Process::result('[keeper] done')]);
        config([
            'blockchain.keeper.enabled' => true,
            'blockchain.keeper.network' => 'sepolia',
            'blockchain.keeper.batch' => 50,
            'blockchain.keeper.claims' => false,
        ]);

        $this->artisan('app:run-blockchain-keeper')
            ->expectsOutputToContain('[keeper] done')
            ->assertSuccessful();

        Process::assertRan(function (PendingProcess $process) {
            $command = implode(' ', (array) $process->command);

            return str_contains($command, 'hardhat run scripts/keeper.ts --network sepolia')
                && $process->environment['KEEPER_TASKS'] === 'queues,development,performance,booster,monthly'
                && $process->environment['KEEPER_BATCH'] === '50';
        });
    }

    public function test_it_adds_claims_when_enabled_and_accepts_explicit_tasks(): void
    {
        Process::fake();
        config(['blockchain.keeper.enabled' => true, 'blockchain.keeper.claims' => true]);

        $this->artisan('app:run-blockchain-keeper')->assertSuccessful();
        $this->artisan('app:run-blockchain-keeper', ['--tasks' => 'queues'])->assertSuccessful();

        Process::assertRan(fn (PendingProcess $p) => str_ends_with($p->environment['KEEPER_TASKS'], ',claims'));
        Process::assertRan(fn (PendingProcess $p) => $p->environment['KEEPER_TASKS'] === 'queues');
    }

    public function test_it_fails_when_the_keeper_fails(): void
    {
        Process::fake(['*' => Process::result(output: '', errorOutput: 'HHE705: network not defined', exitCode: 1)]);
        config(['blockchain.keeper.enabled' => true]);

        $this->artisan('app:run-blockchain-keeper')
            ->expectsOutputToContain('Blockchain keeper failed.')
            ->assertFailed();
    }
}
