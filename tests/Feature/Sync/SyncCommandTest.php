<?php

// -----------------------------------------------------------
//  [*] SyncCommandTest — php artisan bitlitas:sync
//
//  The command line's sync, for the first catch-up and for a
//  cron: it runs syncs of 10,000 blocks until the node's top
//  block is stored, printing where each one got to and how
//  many stored rows got their fees, then the three figures.
//  It exits 0 when it got there, and 1 with the reason when
//  the node failed, when the node is behind the table or
//  when another sync holds the lock. Beside it,
//  bitlitas:prune-cache removes the file cache's expired
//  entries.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Sync;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
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
        $this->assertStringStartsWith('cannot reach the node at http://bitlitas-exit:18281/json_rpc', $lines[0]);
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
    // artisan lists bitlitas:sync and bitlitas:prune-cache,
    // each with what it does.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testCommandIsListedWithItsPurpose(): void
    {
        $commands = $this->app[Kernel::class]->all();

        $this->assertArrayHasKey('bitlitas:sync', $commands);
        $this->assertSame('Copy new blocks and network statistics from the full node', $commands['bitlitas:sync']->getDescription());
        $this->assertSame('Remove the expired entries of the file cache', $commands['bitlitas:prune-cache']->getDescription());
    }






    // -----------------------------------------------------------
    // testCommandFillsInFeesThenStores
    // -----------------------------------------------------------
    //
    // Rows stored without fees get them before the new blocks
    // are stored, and a line says how many.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testCommandFillsInFeesThenStores(): void
    {
        $this->world->chain(0, 1499, false);
        $this->fakeDaemon(['height' => 2500]);

        list($code, $lines) = $this->command();

        $this->assertSame(0, $code);
        $this->assertSame(['Filled in the fees of 1500 stored blocks', 'Stored up to block 2499 of 2499'], array_slice($lines, 0, 2));
        $this->assertStringStartsWith('Mined: ', $lines[2]);
    }






    // -----------------------------------------------------------
    // testNodeBehindTheTableExitsWithOne
    // -----------------------------------------------------------
    //
    // A node lower than the table ends the command with exit
    // code 1 and a line saying where both stand.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testNodeBehindTheTableExitsWithOne(): void
    {
        $this->world->chain(0, 2499);
        $this->fakeDaemon(['height' => 1000]);

        $this->assertSame([1, ['The node is behind the table: its top block is 999, the table holds blocks up to 2499']], $this->command());
    }






    // -----------------------------------------------------------
    // testPruneRemovesOnlyExpiredCacheEntries
    // -----------------------------------------------------------
    //
    // bitlitas:prune-cache deletes an entry of the file cache
    // whose time has run out, and leaves one still running and
    // one kept forever.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testPruneRemovesOnlyExpiredCacheEntries(): void
    {
        $file = Cache::store('file');
        $file->put('prune-test-running', 1, 600);
        $file->forever('prune-test-forever', 1);
        $stale = config('cache.stores.file.path') . '/zz/zz/prune-test-stale';
        @mkdir(dirname($stale), 0777, true);
        file_put_contents($stale, (time() - 60) . serialize(1));

        try {
            list($code, $lines) = $this->console('bitlitas:prune-cache');

            $this->assertSame(0, $code);
            $this->assertMatchesRegularExpression('/^Removed [1-9]\d* expired cache entries$/', $lines[0]);
            $this->assertFileDoesNotExist($stale);
            $this->assertSame([1, 1], [$file->get('prune-test-running'), $file->get('prune-test-forever')]);
        } finally {
            $file->forget('prune-test-running');
            $file->forget('prune-test-forever');
            @unlink($stale);
        }
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
