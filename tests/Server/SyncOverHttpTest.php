<?php

// -----------------------------------------------------------
//  [*] SyncOverHttpTest — the sync as production runs it
//
//  GET /blokai through Apache, whose PHP reaches the node at
//  the address the container's environment gives it —
//  BITLITAS_DAEMON_URL, the stand-in node here, as
//  bitlitas-exit is in production — and stores into MySQL
//  what the next visit to the home page shows. A node error
//  is a 503 in plain words, with the reason in laravel.log;
//  the lock is shared with every other process, a sync run
//  from the command line included; the throttle counts
//  each visitor apart, by the address X-Forwarded-For
//  names; and robots.txt keeps search engines' crawlers
//  off the route, so no crawl sets a sync off.
//
//  Used by:
//    - PHPUnit — the Server suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Server;

use Tests\ServerTestCase;
use Tests\Support\Chain;
use Tests\Support\FakeNodeServer;








// -----------------------------------------------------------
// SyncOverHttpTest
// -----------------------------------------------------------
//
// Requests through ServerClient; the stand-in node is
// configured per test, and its request log read back.
//
// Used by:
//   - PHPUnit — the Server suite (runTests.sh)
// -----------------------------------------------------------

class SyncOverHttpTest extends ServerTestCase
{






    // -----------------------------------------------------------
    // testVisitSyncsFromTheNodeTheEnvironmentNames
    // -----------------------------------------------------------
    //
    // One visit stores the stand-in node's 2,500 blocks: the
    // app asked the node BITLITAS_DAEMON_URL names, at
    // /json_rpc.
    //
    // Used by:
    //   - PHPUnit — the Server suite (runTests.sh)
    // -----------------------------------------------------------

    public function testVisitSyncsFromTheNodeTheEnvironmentNames(): void
    {
        $response = $this->http->get('/blokai');

        $this->assertSame(200, $response->status);
        $this->assertSame(['ok', 2500, 0], [$response->json()['status'], $response->json()['fetched'], $response->json()['remaining']]);
        $this->assertSame(range(0, 2499), $this->world->heights());
        $methods = array_column(array_column(FakeNodeServer::requests(), 'body'), 'method');
        $this->assertSame(['get_info', 'get_block_headers_range', 'get_block_headers_range', 'get_block_headers_range'], $methods);
        $this->assertSame(['/json_rpc'], array_values(array_unique(array_column(FakeNodeServer::requests(), 'path'))));
    }






    // -----------------------------------------------------------
    // testCrawlersAreKeptOffTheSync
    // -----------------------------------------------------------
    //
    // robots.txt asks every crawler to leave /blokai alone: a
    // visit there runs a sync against the node, which a
    // search engine has no business setting off.
    //
    // Used by:
    //   - PHPUnit — the Server suite (runTests.sh)
    // -----------------------------------------------------------

    public function testCrawlersAreKeptOffTheSync(): void
    {
        $response = $this->http->get('/robots.txt');

        $this->assertSame(200, $response->status);
        $this->assertSame(['User-agent: *', 'Disallow: /blokai'], array_values(array_filter(array_map('trim', explode("\n", $response->body)), 'strlen')));
    }






    // -----------------------------------------------------------
    // testHomePageShowsTheSyncedChain
    // -----------------------------------------------------------
    //
    // After the seed and a sync, the home page shows the
    // stand-in chain: its top block and reward, the coins it
    // paid in thousands and as a share, and its difficulty.
    //
    // Used by:
    //   - PHPUnit — the Server suite (runTests.sh)
    // -----------------------------------------------------------

    public function testHomePageShowsTheSyncedChain(): void
    {
        $this->world->seed();
        $this->http->get('/blokai');

        $page = $this->http->get('/')->body;

        $mined = round(Chain::rewardSum(0, 2499) / Chain::ATOMIC_UNITS, 2);
        $this->assertStringContainsString('Dabartinis blokų aukštis: 2499</li>', $page);
        $this->assertStringContainsString('Apdovanojimas už paskutinį bloką: ' . round(Chain::reward(2499) / Chain::ATOMIC_UNITS, 2) . ' LIT</li>', $page);
        $this->assertStringContainsString('Iš 18.3 mln. jau iškasta ' . floor($mined / 1000) . ' tūkst.</p>', $page);
        $this->assertStringContainsString('<div class="progress-value">' . round($mined / 18300000 * 100, 1) . ' %</div>', $page);
        $this->assertStringContainsString('Sunkumas <span class="label label-warning">1,160</span>', $page);
    }






    // -----------------------------------------------------------
    // testNodeErrorIs503InPlainWords
    // -----------------------------------------------------------
    //
    // A node behind a proxy that answers 502: the visit gets a
    // 503 naming nothing internal, and the log gets the reason.
    //
    // Used by:
    //   - PHPUnit — the Server suite (runTests.sh)
    // -----------------------------------------------------------

    public function testNodeErrorIs503InPlainWords(): void
    {
        FakeNodeServer::configure(['http_status' => 502, 'raw_body' => 'Bad Gateway']);

        $written = $this->logWrittenBy(function () use (&$response) {
            $response = $this->http->get('/blokai');
        });

        $this->assertSame(503, $response->status);
        $this->assertSame(['status' => 'error', 'error' => 'The full node did not answer'], $response->json());
        $this->assertStringContainsString('production.WARNING: Bitlitas node sync failed: get_info: the node answered HTTP 502', $written);
    }






    // -----------------------------------------------------------
    // testSyncOfAnotherProcessMakesTheVisitWait
    // -----------------------------------------------------------
    //
    // While this test's process holds the lock — as a
    // bitlitas:sync on the command line would — a visit gets
    // 409 and the node is not asked.
    //
    // Used by:
    //   - PHPUnit — the Server suite (runTests.sh)
    // -----------------------------------------------------------

    public function testSyncOfAnotherProcessMakesTheVisitWait(): void
    {
        $response = $this->whileSyncLockIsHeld(function () {
            return $this->http->get('/blokai');
        });

        $this->assertSame(409, $response->status);
        $this->assertSame(['status' => 'busy'], $response->json());
        $this->assertSame([], FakeNodeServer::requests());
    }






    // -----------------------------------------------------------
    // testThrottleCountsEachVisitorApart
    // -----------------------------------------------------------
    //
    // Ten visits a minute use up the throttle of the address
    // they come from, while another visitor still gets in:
    // the app reads each visitor's own address, the one
    // mod_remoteip takes from X-Forwarded-For.
    //
    // Used by:
    //   - PHPUnit — the Server suite (runTests.sh)
    // -----------------------------------------------------------

    public function testThrottleCountsEachVisitorApart(): void
    {
        FakeNodeServer::configure(['height' => 3]);

        for ($visit = 1; $visit <= 10; $visit++) {
            $this->assertSame(200, $this->http->get('/blokai', ['X-Forwarded-For' => '198.51.100.1'])->status);
        }

        $this->assertSame(429, $this->http->get('/blokai', ['X-Forwarded-For' => '198.51.100.1'])->status);
        $this->assertSame(200, $this->http->get('/blokai', ['X-Forwarded-For' => '198.51.100.2'])->status);
    }
}
