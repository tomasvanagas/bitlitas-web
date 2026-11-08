<?php

// -----------------------------------------------------------
//  [*] NetworkSyncTest — copying the chain from the full node
//
//  NetworkSync asks the node for get_info, stores the blocks
//  the table lacks, oldest first, from the headers of
//  get_block_headers_range — a thousand per call, the most a
//  restricted v0.18 node gives — and recomputes the three
//  figures of the home page. It resumes after the last
//  stored block, swaps seedDB.sh's stand-in block 0 for the
//  real one, checks that the node sent exactly the blocks it
//  asked for before it stores a batch, and keeps the batches
//  stored before a failure. A lock file keeps two syncs from
//  storing the same blocks twice.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Sync;

use App\Blocks;
use App\Services\DaemonException;
use App\Services\NetworkSync;
use Tests\FeatureTestCase;
use Tests\Support\Chain;








// -----------------------------------------------------------
// NetworkSyncTest
// -----------------------------------------------------------
//
// Every test runs the app's own NetworkSync against a node in
// memory (TestCase::fakeDaemon), whose requests it reads
// back.
//
//   sync      — the app's NetworkSync, as the container builds it
//   failedRun — a run that must fail, and its exception
//   mined     — the mined figure the sync stores for a range
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

class NetworkSyncTest extends FeatureTestCase
{






    // -----------------------------------------------------------
    // testFirstSyncStoresTheWholeChain
    // -----------------------------------------------------------
    //
    // On empty tables, one run stores blocks 0 to the top,
    // with the node's rewards and times, after one get_info
    // and a header range per thousand blocks.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testFirstSyncStoresTheWholeChain(): void
    {
        $daemon = $this->fakeDaemon(['height' => 2500]);

        $this->sync()->run();

        $this->assertSame(range(0, 2499), $this->world->heights());
        $this->assertSame([[0, 999], [1000, 1999], [2000, 2499]], $daemon->ranges());
        $this->assertSame('get_info', $daemon->methods()[0]);
        $this->assertSame((string) Chain::rewardSum(0, 2499), (string) Blocks::sum('amount'));
        $block = Blocks::where('block_height', 1234)->first();
        $this->assertSame(Chain::reward(1234), (int) $block->amount);
        $this->assertSame(Chain::timestamp(1234), (int) $block->timestamp);
    }






    // -----------------------------------------------------------
    // testRestrictedNodeIsNeverAskedForTooMany
    // -----------------------------------------------------------
    //
    // A node that refuses more than 1,000 headers a call, as a
    // v0.18 node with --restricted-rpc does, still gives the
    // whole chain: no range asks for more.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testRestrictedNodeIsNeverAskedForTooMany(): void
    {
        $daemon = $this->fakeDaemon(['height' => 3500, 'range_cap' => 1000]);

        $this->sync()->run();

        $this->assertSame(range(0, 3499), $this->world->heights());
        foreach ($daemon->ranges() as list($start, $end)) {
            $this->assertLessThanOrEqual(999, $end - $start);
        }
    }






    // -----------------------------------------------------------
    // testSyncResumesAfterTheLastStoredBlock
    // -----------------------------------------------------------
    //
    // With blocks 0 to 1,499 stored, the next run asks for
    // 1,500 on and nothing before.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testSyncResumesAfterTheLastStoredBlock(): void
    {
        $this->world->chain(0, 1499);
        $daemon = $this->fakeDaemon(['height' => 2500]);

        $this->sync()->run();

        $this->assertSame([[1500, 2499]], $daemon->ranges());
        $this->assertSame(range(0, 2499), $this->world->heights());
    }






    // -----------------------------------------------------------
    // testRunStoresNoMoreThanItsLimit
    // -----------------------------------------------------------
    //
    // A run with a limit stores at most that many blocks and
    // reports what is left; the next runs carry on from there
    // until nothing is.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testRunStoresNoMoreThanItsLimit(): void
    {
        $daemon = $this->fakeDaemon(['height' => 2500]);
        $sync = $this->sync();

        $first = $sync->run(1200);
        $this->assertSame(range(0, 1199), $this->world->heights());
        $this->assertSame([[0, 999], [1000, 1199]], $daemon->ranges());
        $this->assertSame([1200, 1199, 1300], [$first['fetched'], $first['last_stored_block'], $first['remaining']]);

        $second = $sync->run(1200);
        $third = $sync->run(1200);
        $this->assertSame([1200, 100, 100], [$second['fetched'], $second['remaining'], $third['fetched']]);
        $this->assertSame(0, $third['remaining']);
        $this->assertSame(range(0, 2499), $this->world->heights());
    }






    // -----------------------------------------------------------
    // testRunReportsWhatItDid
    // -----------------------------------------------------------
    //
    // A run answers with what it stored, where the table and
    // the node stand, what is left, and the figures it stored:
    // the coins of every stored block in LIT to the hundredth,
    // the difficulty over the block time, the difficulty.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testRunReportsWhatItDid(): void
    {
        $this->fakeDaemon(['height' => 2500, 'difficulty' => 1160, 'target' => 60]);

        $this->assertSame([
            'status'            => 'ok',
            'fetched'           => 1200,
            'last_stored_block' => 1199,
            'node_top_block'    => 2499,
            'remaining'         => 1300,
            'mined'             => $this->mined(0, 1199),
            'hash_rate'         => 19,
            'difficulty'        => 1160,
        ], $this->sync()->run(1200));
    }






    // -----------------------------------------------------------
    // testFiguresAreStoredForTheHomePage
    // -----------------------------------------------------------
    //
    // The three figures land in statistics as text, beside the
    // rows the seed gave them — the seed's zeros replaced.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testFiguresAreStoredForTheHomePage(): void
    {
        $this->world->seed();
        $this->fakeDaemon(['height' => 2500, 'difficulty' => 2400, 'target' => 60]);

        $this->sync()->run();

        $this->assertSame((string) $this->mined(0, 2499), $this->world->statistic('mined'));
        $this->assertSame('40', $this->world->statistic('hash_rate'));
        $this->assertSame('2400', $this->world->statistic('difficulty'));
        $this->assertSame(3, \App\Statistic::count());
    }






    // -----------------------------------------------------------
    // testSeedsStandInGivesWayToTheGenesisBlock
    // -----------------------------------------------------------
    //
    // seedDB.sh's block 0 has no reward. The sync replaces it
    // with the real block 0 — 17.592186044415 LIT, which the
    // mined figure counts — then carries on from block 1.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testSeedsStandInGivesWayToTheGenesisBlock(): void
    {
        $this->world->seed();
        $daemon = $this->fakeDaemon(['height' => 2500]);

        $this->sync()->run();

        $this->assertSame([[0, 0], [1, 1000], [1001, 2000], [2001, 2499]], $daemon->ranges());
        $this->assertSame(range(0, 2499), $this->world->heights());
        $this->assertSame([(string) Chain::GENESIS_REWARD], Blocks::where('block_height', 0)->pluck('amount')->map(function ($amount) {
            return (string) $amount;
        })->all());
        $this->assertSame((string) $this->mined(0, 2499), $this->world->statistic('mined'));
    }






    // -----------------------------------------------------------
    // testBothStandInsOfALaxServerGo
    // -----------------------------------------------------------
    //
    // On a server without strict mode both of seedDB.sh's
    // stand-ins land; the sync leaves one block 0, the real
    // one.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testBothStandInsOfALaxServerGo(): void
    {
        $this->world->placeholderBlock();
        $this->world->placeholderBlock();
        $this->fakeDaemon(['height' => 10]);

        $this->sync()->run();

        $this->assertSame(range(0, 9), $this->world->heights());
        $this->assertSame(Chain::GENESIS_REWARD, (int) Blocks::where('block_height', 0)->value('amount'));
    }






    // -----------------------------------------------------------
    // testRealGenesisBlockIsLeftAlone
    // -----------------------------------------------------------
    //
    // A block 0 stored with its reward is the real one: the
    // run asks for block 1 on.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testRealGenesisBlockIsLeftAlone(): void
    {
        $this->world->block(0, Chain::GENESIS_REWARD);
        $daemon = $this->fakeDaemon(['height' => 10]);

        $this->sync()->run();

        $this->assertSame([[1, 9]], $daemon->ranges());
    }






    // -----------------------------------------------------------
    // testCaughtUpRunOnlyRefreshesTheFigures
    // -----------------------------------------------------------
    //
    // With every block stored, a run asks only for get_info and
    // stores the new difficulty and hash rate.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testCaughtUpRunOnlyRefreshesTheFigures(): void
    {
        $this->world->chain(0, 2499);
        $daemon = $this->fakeDaemon(['height' => 2500, 'difficulty' => 3000]);

        $result = $this->sync()->run();

        $this->assertSame(['get_info'], $daemon->methods());
        $this->assertSame([0, 0], [$result['fetched'], $result['remaining']]);
        $this->assertSame('3000', $this->world->statistic('difficulty'));
        $this->assertSame('50', $this->world->statistic('hash_rate'));
    }






    // -----------------------------------------------------------
    // testHashRateIsZeroWithoutABlockTime
    // -----------------------------------------------------------
    //
    // A node answering target 0 gives a hash rate of 0, not a
    // division by zero.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testHashRateIsZeroWithoutABlockTime(): void
    {
        $this->fakeDaemon(['height' => 5, 'target' => 0]);

        $this->assertSame(0, $this->sync()->run()['hash_rate']);
        $this->assertSame('0', $this->world->statistic('hash_rate'));
    }






    // -----------------------------------------------------------
    // testMinedFigureHoldsTheWholeEmission
    // -----------------------------------------------------------
    //
    // Sums past 2^53 atomic units, where a float loses whole
    // units, still give the coins to the hundredth: three
    // blocks of 900,000 LIT make 2,700,000.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testMinedFigureHoldsTheWholeEmission(): void
    {
        foreach ([0, 1, 2] as $height) {
            $this->world->block($height, 900000 * Chain::ATOMIC_UNITS);
        }
        $this->fakeDaemon(['height' => 3]);

        $this->sync()->run();

        $this->assertSame('2700000', $this->world->statistic('mined'));
    }






    // -----------------------------------------------------------
    // testNodeBehindTheTableTakesNothingAway
    // -----------------------------------------------------------
    //
    // A node lower than the table — resyncing, or another
    // node — gets nothing asked of it beyond get_info, and
    // the stored blocks stay.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testNodeBehindTheTableTakesNothingAway(): void
    {
        $this->world->chain(0, 2499);
        $daemon = $this->fakeDaemon(['height' => 1000]);

        $result = $this->sync()->run();

        $this->assertSame(['get_info'], $daemon->methods());
        $this->assertSame([0, 2499, 999, 0], [$result['fetched'], $result['last_stored_block'], $result['node_top_block'], $result['remaining']]);
        $this->assertCount(2500, $this->world->heights());
    }






    // -----------------------------------------------------------
    // testChainThatGrewIsFollowed
    // -----------------------------------------------------------
    //
    // Blocks the node found after a run are stored by the
    // next one.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testChainThatGrewIsFollowed(): void
    {
        $this->fakeDaemon(['height' => 2500, 'grow' => 10]);
        $sync = $this->sync();

        $sync->run();
        $second = $sync->run();

        $this->assertSame([10, 2509], [$second['fetched'], $second['node_top_block']]);
        $this->assertSame(range(0, 2509), $this->world->heights());
    }






    // -----------------------------------------------------------
    // testDamagedHeadersStoreNothingOfTheirBatch
    // -----------------------------------------------------------
    //
    // When the second range comes back with a gap, a wrong or
    // missing height, a header too few or too many, or a
    // header without its reward or time, the run fails naming
    // the call, nothing of that batch is stored, and the first
    // batch stays.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    /** @dataProvider damage */
    public function testDamagedHeadersStoreNothingOfTheirBatch(string $mode): void
    {
        $this->fakeDaemon(['height' => 2500, 'corrupt' => $mode, 'corrupt_call' => 2]);

        $failure = $this->failedRun();

        $this->assertStringStartsWith('get_block_headers_range: ', $failure->getMessage());
        $this->assertSame(range(0, 999), $this->world->heights());
    }






    // -----------------------------------------------------------
    // damage
    // -----------------------------------------------------------
    //
    // The ways NodeEmulator breaks a range's headers.
    //
    // Used by:
    //   - testDamagedHeadersStoreNothingOfTheirBatch (above)
    // -----------------------------------------------------------

    public function damage(): array
    {
        return [
            'a gap'          => ['gap'],
            'a wrong height' => ['wrong-height'],
            'one too few'    => ['too-few'],
            'one too many'   => ['too-many'],
            'no reward'      => ['no-reward'],
            'no time'        => ['no-timestamp'],
            'no height'      => ['no-height'],
        ];
    }






    // -----------------------------------------------------------
    // testFailedBatchKeepsTheEarlierOnes
    // -----------------------------------------------------------
    //
    // A node error on the second range fails the run with the
    // node's message; the first thousand blocks stay, and no
    // figure is stored.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testFailedBatchKeepsTheEarlierOnes(): void
    {
        $this->fakeDaemon(['height' => 2500, 'error_call' => 2]);

        $failure = $this->failedRun();

        $this->assertSame("get_block_headers_range: Internal error: can't get block by height. Height = 1000.", $failure->getMessage());
        $this->assertSame(range(0, 999), $this->world->heights());
        $this->assertNull($this->world->statistic('mined'));
    }






    // -----------------------------------------------------------
    // testUnreachableNodeChangesNothing
    // -----------------------------------------------------------
    //
    // When the node cannot be reached, or answers BUSY, the
    // run fails before touching a row: the seed's stand-in and
    // zeros stay.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testUnreachableNodeChangesNothing(): void
    {
        $this->world->seed();
        $this->fakeDaemon()->unreachable = true;
        $this->assertStringStartsWith('cannot reach the node at ', $this->failedRun()->getMessage());

        $this->fakeDaemon(['status' => 'BUSY']);
        $this->assertSame('get_info: the node is BUSY', $this->failedRun()->getMessage());

        $this->assertSame([0], $this->world->heights());
        $this->assertSame(0, (int) Blocks::value('amount'));
        $this->assertSame('0', $this->world->statistic('mined'));
    }






    // -----------------------------------------------------------
    // testSecondSyncWaitsItsTurn
    // -----------------------------------------------------------
    //
    // While another sync holds the lock, a run answers busy
    // and asks the node nothing.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testSecondSyncWaitsItsTurn(): void
    {
        $daemon = $this->fakeDaemon();

        $result = $this->whileSyncLockIsHeld(function () {
            return $this->sync()->run();
        });

        $this->assertSame(['status' => 'busy'], $result);
        $this->assertSame([], $daemon->requests);
    }






    // -----------------------------------------------------------
    // testFailedRunLetsGoOfTheLock
    // -----------------------------------------------------------
    //
    // A run that failed releases the lock: the next one runs.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testFailedRunLetsGoOfTheLock(): void
    {
        $daemon = $this->fakeDaemon(['height' => 5]);
        $daemon->unreachable = true;
        $this->failedRun();

        $daemon->unreachable = false;

        $this->assertSame('ok', $this->sync()->run()['status']);
    }






    // -----------------------------------------------------------
    // testLockFileIsOpenToEveryUser
    // -----------------------------------------------------------
    //
    // The lock file takes read and write from every user:
    // php-fpm serves as www-data while bitlitas:sync may run
    // as root, and whichever creates the file first must not
    // lock the other out.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testLockFileIsOpenToEveryUser(): void
    {
        @unlink(storage_path('framework/bitlitas-sync.lock'));
        $this->fakeDaemon(['height' => 1]);

        $this->sync()->run();

        clearstatcache();
        $this->assertSame('0666', substr(sprintf('%o', fileperms(storage_path('framework/bitlitas-sync.lock'))), -4));
    }






    // -----------------------------------------------------------
    // sync
    // -----------------------------------------------------------
    //
    // NetworkSync as the container builds it — with the node
    // the test bound.
    //
    // Used by:
    //   - the tests above
    // -----------------------------------------------------------

    private function sync(): NetworkSync
    {
        return $this->app->make(NetworkSync::class);
    }






    // -----------------------------------------------------------
    // failedRun
    // -----------------------------------------------------------
    //
    // Runs a sync that must fail, and returns its
    // DaemonException for the test to read.
    //
    // Used by:
    //   - the tests above
    // -----------------------------------------------------------

    private function failedRun(): DaemonException
    {
        try {
            $this->sync()->run();
        } catch (DaemonException $failure) {
            return $failure;
        }
        $this->fail('the run did not fail');
    }






    // -----------------------------------------------------------
    // mined
    // -----------------------------------------------------------
    //
    // The coins blocks $from to $to pay, in LIT to the
    // hundredth — the mined figure for those rows.
    //
    // Used by:
    //   - the tests above
    // -----------------------------------------------------------

    private function mined(int $from, int $to): float
    {
        return round(Chain::rewardSum($from, $to) / Chain::ATOMIC_UNITS, 2);
    }
}
