<?php

// -----------------------------------------------------------
//  [*] FakeNodeServer — steers the stand-in node over HTTP
//
//  The test side of tests/container/fake-node.php, the node
//  run.sh starts on 127.0.0.1:18081 for php-fpm and for
//  DaemonClientTest: a test writes the node's state to
//  /tmp/fake-node/state.json and reads back every request
//  the node logged to /tmp/fake-node/requests.jsonl. Both
//  start over with every configure, so no test sees another
//  test's node.
//
//  Used by:
//    - ServerTestCase (tests/) — a fresh node for every test
//    - DaemonClientTest (Feature/Node/)
//    - the Server suite (Server/)
// -----------------------------------------------------------


namespace Tests\Support;








// -----------------------------------------------------------
// FakeNodeServer
// -----------------------------------------------------------
//
// Static: there is one stand-in node in the container.
//
//   configure — a fresh node with this state
//   reset     — a fresh node with the default state
//   state     — the node's state now, counters included
//   requests  — what the node was sent since configure
//
// Used by:
//   - ServerTestCase (tests/)
//   - DaemonClientTest (Feature/Node/)
//   - the Server suite (Server/)
// -----------------------------------------------------------

class FakeNodeServer
{

    // Where run.sh starts the node, and the folder it shares
    // with the tests
    public const URL = 'http://127.0.0.1:18081';
    private const DIR = '/tmp/fake-node';






    // -----------------------------------------------------------
    // configure
    // -----------------------------------------------------------
    //
    // Writes the state the node answers the next requests
    // from, and empties its request log.
    //
    // Used by:
    //   - reset (below)
    //   - DaemonClientTest (Feature/Node/), the Server suite
    // -----------------------------------------------------------

    public static function configure(array $state): void
    {
        file_put_contents(self::DIR . '/state.json', json_encode((object) $state));
        file_put_contents(self::DIR . '/requests.jsonl', '');
    }






    // -----------------------------------------------------------
    // reset
    // -----------------------------------------------------------
    //
    // The default node: 2,500 blocks, difficulty 1,160, no
    // login.
    //
    // Used by:
    //   - ServerTestCase (tests/), DaemonClientTest
    //     (Feature/Node/) — before and after every test
    // -----------------------------------------------------------

    public static function reset(): void
    {
        self::configure([]);
    }






    // -----------------------------------------------------------
    // state
    // -----------------------------------------------------------
    //
    // The state as the node last stored it, over the defaults.
    //
    // Used by:
    //   - the Server suite
    // -----------------------------------------------------------

    public static function state(): array
    {
        $stored = json_decode((string) file_get_contents(self::DIR . '/state.json'), true) ?: [];
        return (new NodeEmulator($stored))->state;
    }






    // -----------------------------------------------------------
    // requests
    // -----------------------------------------------------------
    //
    // Every request since configure, oldest first: its HTTP
    // method, path, content type, the scheme of its
    // Authorization header (or null), and its body as sent
    // (raw) and decoded (body).
    //
    // Used by:
    //   - DaemonClientTest (Feature/Node/), the Server suite
    // -----------------------------------------------------------

    public static function requests(): array
    {
        $requests = [];
        foreach (file(self::DIR . '/requests.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $requests[] = json_decode($line, true);
        }
        return $requests;
    }
}
