<?php

// -----------------------------------------------------------
//  [*] BlocksRouteTest — GET /blokai, the sync's web door
//
//  Each visit to /blokai runs a sync of at most 5,000 blocks
//  — well inside PHP's 30-second limit — and answers with the
//  run's report as JSON: 200 when it ran, 409 while another
//  sync holds the lock, 503 when the node failed. The 503
//  names the node's error only in debug mode, since it holds
//  internal addresses, and the error goes to Laravel's log
//  either way. The route takes ten visits a minute; every
//  one of them reaches the node.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Sync;

use Tests\FeatureTestCase;
use Tests\Support\Chain;








// -----------------------------------------------------------
// BlocksRouteTest
// -----------------------------------------------------------
//
// In-process requests with a node in memory
// (TestCase::fakeDaemon).
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

class BlocksRouteTest extends FeatureTestCase
{






    // -----------------------------------------------------------
    // testVisitSyncsAndReportsInJson
    // -----------------------------------------------------------
    //
    // A visit stores the chain and answers 200 with the run's
    // report.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testVisitSyncsAndReportsInJson(): void
    {
        $this->fakeDaemon(['height' => 2500]);

        $response = $this->get('/blokai');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertExactJson([
            'status'            => 'ok',
            'fetched'           => 2500,
            'filled'            => 0,
            'last_stored_block' => 2499,
            'node_top_block'    => 2499,
            'remaining'         => 0,
            'mined'             => round(Chain::rewardSum(0, 2499) / Chain::ATOMIC_UNITS, 2),
            'hash_rate'         => 19,
            'difficulty'        => 1160,
        ]);
        $this->assertSame(range(0, 2499), $this->world->heights());
    }






    // -----------------------------------------------------------
    // testVisitStoresAtMostFiveThousandBlocks
    // -----------------------------------------------------------
    //
    // On a node of 12,000 blocks, a visit stores 5,000 and
    // reports 7,000 left.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testVisitStoresAtMostFiveThousandBlocks(): void
    {
        $this->fakeDaemon(['height' => 12000]);

        $response = $this->get('/blokai');

        $response->assertJson(['fetched' => 5000, 'last_stored_block' => 4999, 'remaining' => 7000]);
        $this->assertCount(5000, $this->world->heights());
    }






    // -----------------------------------------------------------
    // testNodeFailureIs503WithoutDetails
    // -----------------------------------------------------------
    //
    // With debug mode off, a node that cannot be reached gets
    // a 503 that says so in plain words, naming no address.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testNodeFailureIs503WithoutDetails(): void
    {
        $this->fakeDaemon()->unreachable = true;

        $response = $this->get('/blokai');

        $response->assertStatus(503);
        $response->assertExactJson(['status' => 'error', 'error' => 'The full node did not answer']);
        $response->assertDontSee('bitlitas-exit');
        $response->assertDontSee('json_rpc');
    }






    // -----------------------------------------------------------
    // testDebugModeNamesTheNodesError
    // -----------------------------------------------------------
    //
    // With debug mode on, the 503 carries the node's error as
    // the sync met it.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testDebugModeNamesTheNodesError(): void
    {
        config(['app.debug' => true]);
        $this->fakeDaemon(['http_status' => 502]);

        $this->get('/blokai')->assertStatus(503)->assertExactJson([
            'status' => 'error',
            'error'  => 'get_info: the node answered HTTP 502',
        ]);
    }






    // -----------------------------------------------------------
    // testNodeFailureIsLogged
    // -----------------------------------------------------------
    //
    // The error the visitor is spared is written to Laravel's
    // log as a warning, with the node's address.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testNodeFailureIsLogged(): void
    {
        $this->fakeDaemon()->unreachable = true;

        $written = $this->logWrittenBy(function () {
            $this->get('/blokai');
        });

        $this->assertStringContainsString('.WARNING: Bitlitas node sync failed: cannot reach the node at http://bitlitas-exit:18281/json_rpc', $written);
    }






    // -----------------------------------------------------------
    // testVisitDuringASyncIs409
    // -----------------------------------------------------------
    //
    // While another sync holds the lock, a visit answers 409
    // busy and the node is not asked.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testVisitDuringASyncIs409(): void
    {
        $daemon = $this->fakeDaemon();

        $response = $this->whileSyncLockIsHeld(function () {
            return $this->get('/blokai');
        });

        $response->assertStatus(409);
        $response->assertExactJson(['status' => 'busy']);
        $this->assertSame([], $daemon->requests);
    }






    // -----------------------------------------------------------
    // testNodeBehindTheTableIs503WithTheReport
    // -----------------------------------------------------------
    //
    // A node lower than the table is a 503 whose report says
    // behind and where the node and the table stand.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testNodeBehindTheTableIs503WithTheReport(): void
    {
        $this->world->chain(0, 2499);
        $this->fakeDaemon(['height' => 1000]);

        $response = $this->get('/blokai');

        $response->assertStatus(503);
        $response->assertExactJson([
            'status'            => 'behind',
            'fetched'           => 0,
            'filled'            => 0,
            'last_stored_block' => 2499,
            'node_top_block'    => 999,
            'remaining'         => 0,
        ]);
    }






    // -----------------------------------------------------------
    // testTenVisitsAMinuteReachTheNode
    // -----------------------------------------------------------
    //
    // Ten visits in a minute each run a sync; the eleventh is
    // refused with 429 and asks the node nothing.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testTenVisitsAMinuteReachTheNode(): void
    {
        $daemon = $this->fakeDaemon(['height' => 3]);

        for ($visit = 1; $visit <= 10; $visit++) {
            $this->get('/blokai')->assertStatus(200);
        }
        $asked = count($daemon->requests);

        $this->get('/blokai')->assertStatus(429);
        $this->assertSame($asked, count($daemon->requests));
        $this->assertSame(10, $daemon->node->state['calls']['get_info']);
    }






    // -----------------------------------------------------------
    // testRouteTakesOnlyGets
    // -----------------------------------------------------------
    //
    // A POST to /blokai is refused with 405 before any sync.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testRouteTakesOnlyGets(): void
    {
        $daemon = $this->fakeDaemon();

        $this->post('/blokai')->assertStatus(405);
        $this->assertSame([], $daemon->requests);
    }
}
