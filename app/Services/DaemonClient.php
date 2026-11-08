<?php

// -----------------------------------------------------------
//  [*] DaemonClient — the client of the full node
//
//  Talks to a Bitlitas full node (bitlitasd) over its JSON-RPC
//  interface, the one the 2018 block explorer used. It makes
//  only calls that work on a node started with
//  --restricted-rpc, and that answer even while the node does
//  not count as synchronized with its peers:
//  get_last_block_header, for one, answers BUSY then, and a
//  lone node mining its own chain may never count as
//  synchronized. Every failure is a DaemonException naming the
//  call.
//
//  Used by:
//    - AppServiceProvider — binds one per request, from
//      config/services.php
//    - NetworkSync (this folder) — the sync's node
// -----------------------------------------------------------


namespace App\Services;








// -----------------------------------------------------------
// DaemonClient
// -----------------------------------------------------------
//
// One client per node address; post is the only method that
// touches the network, which lets the test suite put a node
// of its own behind the rest.
//
//   __construct          — the node's address and login
//   getInfo              — the node's view of the chain
//   getBlockHeadersRange — the headers of a run of blocks
//   call                 — one JSON-RPC call
//   post                 — one HTTP POST to the node
//
// Used by:
//   - AppServiceProvider, NetworkSync (this folder)
// -----------------------------------------------------------

class DaemonClient
{

    // Seconds allowed to connect, and for a whole call
    const CONNECT_TIMEOUT = 5;
    const TIMEOUT = 30;

    // The node's JSON-RPC address, the base URL with
    // /json_rpc appended
    private $endpoint;

    // "user:password" for a node started with --rpc-login, or
    // null for none
    private $login;






    // -----------------------------------------------------------
    // __construct
    // -----------------------------------------------------------
    //
    // A client for the node's RPC address — a closing slash is
    // dropped — with a login, when the node asks for one. An
    // empty login counts as none, so a BITLITAS_DAEMON_LOGIN
    // set but empty sends no credentials.
    //
    // Used by:
    //   - AppServiceProvider::register
    // -----------------------------------------------------------

    public function __construct($url, $login = null)
    {
        $this->endpoint = rtrim($url, '/') . '/json_rpc';
        $this->login = $login === '' ? null : $login;
    }






    // -----------------------------------------------------------
    // getInfo
    // -----------------------------------------------------------
    //
    // get_info's result: among its fields the height (the
    // number of blocks, so the top block is height − 1), the
    // difficulty of the next block and the target, the seconds
    // per block. A result missing one of those three is refused,
    // naming it.
    //
    // Used by:
    //   - NetworkSync::sync (this folder)
    // -----------------------------------------------------------

    public function getInfo()
    {
        $info = $this->call('get_info');

        foreach (['height', 'difficulty', 'target'] as $field) {
            if (!isset($info[$field])) {
                throw new DaemonException("get_info: the answer has no $field");
            }
        }

        return $info;
    }






    // -----------------------------------------------------------
    // getBlockHeadersRange
    // -----------------------------------------------------------
    //
    // The headers of blocks $start to $end, both included,
    // oldest first; the heights go out as numbers, whatever
    // they were given as. Each header has the block's height,
    // its time and its reward — the atomic units its miner
    // received, new coins plus fees. A restricted v0.18 node
    // refuses more than 1001 headers a call.
    //
    // Used by:
    //   - NetworkSync::sync, NetworkSync::replaceSeededGenesis
    //     (this folder)
    // -----------------------------------------------------------

    public function getBlockHeadersRange($start, $end)
    {
        $result = $this->call('get_block_headers_range', [
            'start_height' => (int) $start,
            'end_height'   => (int) $end,
        ]);

        if (!isset($result['headers']) || !is_array($result['headers'])) {
            throw new DaemonException('get_block_headers_range: the answer has no headers');
        }

        return $result['headers'];
    }






    // -----------------------------------------------------------
    // call
    // -----------------------------------------------------------
    //
    // One JSON-RPC call and its result. The params always go
    // out as a JSON object, an empty one included, as the
    // node's parser wants; numbers beyond PHP's integers come
    // back as exact text. Every way the call can fail — a
    // refused login, an HTTP error, an answer that is no
    // JSON-RPC result, the node's own error, a status other
    // than OK — is a DaemonException naming the method.
    //
    // Used by:
    //   - getInfo, getBlockHeadersRange (above)
    // -----------------------------------------------------------

    public function call($method, array $params = [])
    {
        // STEP 1: the request, as JSON-RPC 2.0
        // ====================================
        list($status, $body) = $this->post(json_encode([
            'jsonrpc' => '2.0',
            'id'      => '0',
            'method'  => $method,
            'params'  => (object) $params,
        ]));


        // STEP 2: the HTTP answer — 401 is the login's fault
        // ==================================================
        if ($status === 401) {
            throw new DaemonException("$method: the node refused the login (BITLITAS_DAEMON_LOGIN)");
        }

        if ($status !== 200) {
            throw new DaemonException("$method: the node answered HTTP $status");
        }


        // STEP 3: the JSON-RPC answer — the node's error, or a
        // result with an OK status
        // ====================================================
        $answer = json_decode($body, true, 512, JSON_BIGINT_AS_STRING);

        if (isset($answer['error'])) {
            $error = $answer['error'];

            throw new DaemonException("$method: " . (isset($error['message']) ? $error['message'] : json_encode($error)));
        }

        if (!isset($answer['result']) || !is_array($answer['result'])) {
            throw new DaemonException("$method: the answer is not a JSON-RPC result");
        }

        $result = $answer['result'];

        // BUSY while the node is still starting up (or, for some calls, syncing)
        if (isset($result['status']) && $result['status'] !== 'OK') {
            throw new DaemonException("$method: the node is {$result['status']}");
        }

        return $result;
    }






    // -----------------------------------------------------------
    // post
    // -----------------------------------------------------------
    //
    // POSTs the JSON body to the node and returns the HTTP
    // status and the response body, with CONNECT_TIMEOUT to
    // connect and TIMEOUT for the whole call. With a login, curl
    // answers the node's digest challenge. A request that got
    // no HTTP answer at all — nothing listening, a timeout — is
    // a DaemonException naming the address and curl's reason.
    //
    // Used by:
    //   - call (above)
    //   - FakeDaemon (tests/Support) — overrides it with a node
    //     in memory
    // -----------------------------------------------------------

    protected function post($body)
    {
        $curl = curl_init($this->endpoint);

        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
        ]);

        if ($this->login !== null) {
            // --rpc-login uses HTTP digest authentication
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
            curl_setopt($curl, CURLOPT_USERPWD, $this->login);
        }

        $response = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($response === false) {
            throw new DaemonException("cannot reach the node at {$this->endpoint}: $error");
        }

        return [$status, $response];
    }
}
