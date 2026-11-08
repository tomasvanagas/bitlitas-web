<?php

// -----------------------------------------------------------
//  [*] SyncCommandTest — php artisan bitlitas:sync
//
//  The command line's sync, for the first catch-up and for a
//  cron: it runs syncs of 10,000 blocks until the node's top
//  block is stored, printing where each one got to, then the
//  three figures. It exits 0 when it got there, and 1 with
//  the reason when the node failed or another sync holds the
//  lock.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Sync;

use Illuminate\Contracts\Console\Kernel;
use Tests\FeatureTestCase;
use Tests\Support\Chain;








// -----------------------------------------------------------
// SyncCommandTest
// -----------------------------------------------------------
//
// Runs the command through the console kernel, in-process,
// with a node in memory (TestCase::fakeDaemon).
//
//   command — the exit code and the printed lines of one run
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

class SyncCommandTest extends FeatureTestCase
{






    // -----------------------------------------------------------
    // testCommandSyncsToTheTopInTenThousands
    // -----------------------------------------------------------
    //
    // A node of 25,000 blocks takes three syncs, each line
    // saying where it got to, then a line of figures; the
    // command exits 0.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testCommandSyncsToTheTopInTenThousands(): void
    {
        $this->fakeDaemon(['height' => 25000]);

        list($code, $lines) = $this->command();

        $this->assertSame(0, $code);
        $this->assertSame([
            'Stored up to block 9999 of 24999',
            'Stored up to block 19999 of 24999',
            'Stored up to block 24999 of 24999',
            'Mined: ' . round(Chain::rewardSum(0, 24999) / Chain::ATOMIC_UNITS, 2) . ' LIT, hash rate: 19 H/s, difficulty: 1160',
        ], $lines);
        $this->assertCount(25000, $this->world->heights());
    }






    // -----------------------------------------------------------
    // testCommandFollowsAGrowingChain
    // -----------------------------------------------------------
    //
    // Blocks found while it runs are taken too: it stops at
    // the top the last get_info reported.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testCommandFollowsAGrowingChain(): void
    {
        $this->fakeDaemon(['height' => 25000, 'grow' => 10]);

        list($code, $lines) = $this->command();

        $this->assertSame(0, $code);
        $this->assertSame('Stored up to block 25019 of 25019', $lines[2]);
        $this->assertCount(25020, $this->world->heights());
    }






    // -----------------------------------------------------------
    // testCaughtUpCommandPrintsTheFigures
    // -----------------------------------------------------------
    //
    // With nothing left to store it still reports and prints
    // the figures.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testCaughtUpCommandPrintsTheFigures(): void
    {
        $this->world->chain(0, 99);
        $this->fakeDaemon(['height' => 100]);

        list($code, $lines) = $this->command();

        $this->assertSame(0, $code);
        $this->assertSame('Stored up to block 99 of 99', $lines[0]);
        $this->assertStringStartsWith('Mined: ', $lines[1]);
    }






    // -----------------------------------------------------------
    // testNodeFailureExitsWithItsReason
    // -----------------------------------------------------------
    //
    // A node that cannot be reached ends the command with
    // exit code 1 and the error, address included — the
    // command line is the operator's.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testNodeFailureExitsWithItsReason(): void
    {
        $this->fakeDaemon()->unreachable = true;

        list($code, $lines) = $this->command();

        $this->assertSame(1, $code);
        $this->assertStringStartsWith('cannot reach the node at http://bitlitas-exit:18081/json_rpc', $lines[0]);
    }






    // -----------------------------------------------------------
    // testCommandDuringASyncExitsWithOne
    // -----------------------------------------------------------
    //
    // While another sync holds the lock, the command says so
    // and exits 1.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testCommandDuringASyncExitsWithOne(): void
    {
        $this->fakeDaemon();

        list($code, $lines) = $this->whileSyncLockIsHeld(function () {
            return $this->command();
        });

        $this->assertSame([1, ['Another sync is running']], [$code, $lines]);
    }






    // -----------------------------------------------------------
    // testCommandIsListedWithItsPurpose
    // -----------------------------------------------------------
    //
    // artisan lists bitlitas:sync with what it does.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testCommandIsListedWithItsPurpose(): void
    {
        $commands = $this->app[Kernel::class]->all();

        $this->assertArrayHasKey('bitlitas:sync', $commands);
        $this->assertSame('Copy new blocks and network statistics from the full node', $commands['bitlitas:sync']->getDescription());
    }






    // -----------------------------------------------------------
    // command
    // -----------------------------------------------------------
    //
    // Runs bitlitas:sync once, as `php artisan bitlitas:sync`
    // would (TestCase::console); returns its exit code and its
    // printed lines.
    //
    // Used by:
    //   - the tests above
    // -----------------------------------------------------------

    private function command(): array
    {
        return $this->console('bitlitas:sync');
    }
}
