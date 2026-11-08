<?php

// -----------------------------------------------------------
//  [*] FakeDaemon — the app's node client, with a node in memory
//
//  DaemonClient with only its transport replaced: post, the
//  one method that touches the network, asks a NodeEmulator
//  instead of curl. Everything above it — the JSON-RPC and
//  /get_transactions requests the client builds, how it
//  reads the answers, every check it makes — runs as it does
//  in production. Bound in
//  the container in place of the real client, it gives a
//  Feature test a node it fully controls, and keeps every
//  request it was sent.
//
//  Used by:
//    - TestCase::fakeDaemon (tests/)
//    - the Feature suite (Feature/Sync/, Feature/Site/)
// -----------------------------------------------------------


namespace Tests\Support;

use App\Services\DaemonClient;
use App\Services\DaemonException;








// -----------------------------------------------------------
// FakeDaemon
// -----------------------------------------------------------
//
// Its node and its request log are public, so a test steers
// the one and reads the other.
//
//   post         — the node's answer to one request
//   methods      — the JSON-RPC methods asked for, in order
//   ranges       — the header ranges asked for, in order
//   transactions — the transactions asked for, call by call
//
// Used by:
//   - TestCase::fakeDaemon (tests/)
//   - the Feature suite
// -----------------------------------------------------------

class FakeDaemon extends DaemonClient
{

    // The address it pretends to call: the one
    // docker-compose.yml gives production
    public const URL = 'http://bitlitas-exit:18281';

    // The node in memory, and every request it was sent,
    // decoded — a request to a path other than /json_rpc
    // carries that path under "path"
    public $node;
    public $requests = [];

    // When true, every request fails as one to a node that is
    // down
    public $unreachable = false;






    // -----------------------------------------------------------
    // __construct
    // -----------------------------------------------------------
    //
    // A client for URL with a node of the given state.
    //
    // Used by:
    //   - TestCase::fakeDaemon (tests/)
    // -----------------------------------------------------------

    public function __construct(array $state = [])
    {
        parent::__construct(self::URL);
        $this->node = new NodeEmulator($state);
    }






    // -----------------------------------------------------------
    // post
    // -----------------------------------------------------------
    //
    // Records the request and hands it to the node; the
    // DaemonException of a failed connection when the node is
    // unreachable.
    //
    // Used by:
    //   - DaemonClient::call, DaemonClient::request — every
    //     request
    // -----------------------------------------------------------

    protected function post($path, $body)
    {
        $request = json_decode($body, true);
        $this->requests[] = $path === '/json_rpc' ? $request : ['path' => $path] + $request;
        if ($this->unreachable) {
            throw new DaemonException('cannot reach the node at ' . self::URL . $path . ': Failed to connect to bitlitas-exit port 18281: Connection refused');
        }
        return $this->node->answer($body, $path);
    }






    // -----------------------------------------------------------
    // methods
    // -----------------------------------------------------------
    //
    // The method of every JSON-RPC request, in the order
    // sent.
    //
    // Used by:
    //   - the Feature suite
    // -----------------------------------------------------------

    public function methods(): array
    {
        return array_column($this->requests, 'method');
    }






    // -----------------------------------------------------------
    // ranges
    // -----------------------------------------------------------
    //
    // [start, end] of every get_block_headers_range request,
    // in the order sent.
    //
    // Used by:
    //   - the Feature suite
    // -----------------------------------------------------------

    public function ranges(): array
    {
        $ranges = [];
        foreach ($this->requests as $request) {
            if (($request['method'] ?? null) === 'get_block_headers_range') {
                $ranges[] = [$request['params']['start_height'], $request['params']['end_height']];
            }
        }
        return $ranges;
    }






    // -----------------------------------------------------------
    // transactions
    // -----------------------------------------------------------
    //
    // The hashes every /get_transactions request asked for,
    // one list per request, in the order sent.
    //
    // Used by:
    //   - the Feature suite
    // -----------------------------------------------------------

    public function transactions(): array
    {
        $asked = [];
        foreach ($this->requests as $request) {
            if (($request['path'] ?? null) === '/get_transactions') {
                $asked[] = $request['txs_hashes'];
            }
        }
        return $asked;
    }
}
