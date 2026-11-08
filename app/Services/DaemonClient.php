<?php

// -----------------------------------------------------------
//  [*] DaemonClient — the client of the full node
//
//  Talks to a Bitlitas full node (bitlitasd) — the v0.18 one
//  the stack names, though a v0.11 node answers the same —
//  over its JSON-RPC interface and its plain JSON
//  /get_transactions. It makes only calls that work on a
//  node started with --restricted-rpc, within the limits
//  such a node sets, and that answer even while the node
//  does not count as synchronized with its peers:
//  get_last_block_header, for one, answers BUSY then on
//  v0.11, and a lone node mining its own chain may never
//  count as synchronized. get_coinbase_tx_sum, which would
//  sum a run of blocks' new coins in one call, is refused by
//  a restricted node, so a block's fees come from its
//  transactions instead. Every failure is a DaemonException
//  naming the call.
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
//   getBlockTransactions — the transactions of one block
//   getTransactionFees   — the fees of transactions
//   call                 — one JSON-RPC call
//   request              — one call of a plain JSON path
//   fee                  — the fee of one decoded transaction
//   post                 — one HTTP POST to the node
//
// Used by:
//   - AppServiceProvider, NetworkSync (this folder)
// -----------------------------------------------------------

class DaemonClient
{

    // Seconds allowed to connect, and for a whole call
    private const CONNECT_TIMEOUT = 5;
    private const TIMEOUT = 30;

    // The most transactions a restricted node gives in one
    // get_transactions call (RESTRICTED_TRANSACTIONS_COUNT)
    private const TRANSACTIONS_PER_CALL = 100;

    // The node's RPC address, without a closing slash; the
    // paths of the calls are appended to it
    private $url;

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
        $this->url = rtrim($url, '/');
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
    // its time, its reward — the atomic units its miner
    // received, new coins plus fees — and num_txes, how many
    // transactions besides the miner's it holds. A restricted
    // node refuses more than 1001 headers a call.
    //
    // Used by:
    //   - NetworkSync::fetchChecked (this folder)
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
    // getBlockTransactions
    // -----------------------------------------------------------
    //
    // The hashes of the transactions in the block at $height,
    // the miner's own left out, from get_block. The node
    // leaves tx_hashes out of the answer for a block that
    // holds none, which reads as an empty list.
    //
    // Used by:
    //   - NetworkSync::fees (this folder)
    // -----------------------------------------------------------

    public function getBlockTransactions($height)
    {
        $result = $this->call('get_block', ['height' => (int) $height]);
        $hashes = isset($result['tx_hashes']) ? $result['tx_hashes'] : [];

        if (!is_array($hashes)) {
            throw new DaemonException("get_block: the tx_hashes of block $height are no list");
        }

        return array_values($hashes);
    }






    // -----------------------------------------------------------
    // getTransactionFees
    // -----------------------------------------------------------
    //
    // The fee of each transaction named, in atomic units, by
    // hash, from /get_transactions with the transactions
    // decoded as JSON — TRANSACTIONS_PER_CALL at a time, the
    // most a restricted node gives. A transaction the node
    // does not know, or does not answer for, is refused.
    //
    // Used by:
    //   - NetworkSync::fees (this folder)
    // -----------------------------------------------------------

    public function getTransactionFees(array $hashes)
    {
        $fees = [];

        foreach (array_chunk(array_values($hashes), self::TRANSACTIONS_PER_CALL) as $chunk) {
            $result = $this->request('/get_transactions', ['txs_hashes' => $chunk, 'decode_as_json' => true]);

            if (!empty($result['missed_tx'])) {
                throw new DaemonException('/get_transactions: the node does not know ' . implode(', ', (array) $result['missed_tx']));
            }

            if (!isset($result['txs']) || !is_array($result['txs'])) {
                throw new DaemonException('/get_transactions: the answer has no txs');
            }

            foreach ($result['txs'] as $transaction) {
                if (isset($transaction['tx_hash'])) {
                    $fees[$transaction['tx_hash']] = $this->fee($transaction);
                }
            }
        }

        foreach ($hashes as $hash) {
            if (!isset($fees[$hash])) {
                throw new DaemonException("/get_transactions: no answer for transaction $hash");
            }
        }

        return $fees;
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
    //   - getInfo, getBlockHeadersRange, getBlockTransactions
    //     (above)
    // -----------------------------------------------------------

    public function call($method, array $params = [])
    {
        // STEP 1: the request, as JSON-RPC 2.0
        // ====================================
        list($status, $body) = $this->post('/json_rpc', json_encode([
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
    // request
    // -----------------------------------------------------------
    //
    // One call of a path the node answers in plain JSON rather
    // than JSON-RPC — the params are the body, the answer is
    // the result — refused the way call refuses, naming the
    // path: a refused login, an HTTP error, an answer that is
    // no JSON object, a status other than OK, which is how a
    // restricted node turns down a request over its limits.
    //
    // Used by:
    //   - getTransactionFees (above)
    // -----------------------------------------------------------

    public function request($path, array $params)
    {
        list($status, $body) = $this->post($path, json_encode((object) $params));

        if ($status === 401) {
            throw new DaemonException("$path: the node refused the login (BITLITAS_DAEMON_LOGIN)");
        }

        if ($status !== 200) {
            throw new DaemonException("$path: the node answered HTTP $status");
        }

        $answer = json_decode($body, true, 512, JSON_BIGINT_AS_STRING);

        if (!is_array($answer)) {
            throw new DaemonException("$path: the answer is no JSON object");
        }

        if (isset($answer['status']) && $answer['status'] !== 'OK') {
            throw new DaemonException("$path: the node is {$answer['status']}");
        }

        return $answer;
    }






    // -----------------------------------------------------------
    // fee
    // -----------------------------------------------------------
    //
    // The fee of one transaction of /get_transactions, from its
    // as_json. A RingCT transaction states it, as
    // rct_signatures.txnFee; one from before RingCT — every
    // transaction of a chain still on hard fork 1 — carries its
    // amounts in the clear, and the fee is what its inputs
    // bring in beyond what its outputs pay out. A transaction
    // that cannot be read, or whose outputs pay out more than
    // its inputs bring, is refused.
    //
    // Used by:
    //   - getTransactionFees (above)
    // -----------------------------------------------------------

    private function fee(array $transaction)
    {
        $hash = $transaction['tx_hash'];
        $json = isset($transaction['as_json']) ? json_decode($transaction['as_json'], true, 512, JSON_BIGINT_AS_STRING) : null;

        if (!is_array($json)) {
            throw new DaemonException("/get_transactions: transaction $hash comes without as_json");
        }

        if (isset($json['rct_signatures']['txnFee'])) {
            return (int) $json['rct_signatures']['txnFee'];
        }

        $in = 0;
        foreach (isset($json['vin']) ? $json['vin'] : [] as $input) {
            $in += isset($input['key']['amount']) ? (int) $input['key']['amount'] : 0;
        }

        $out = 0;
        foreach (isset($json['vout']) ? $json['vout'] : [] as $output) {
            $out += isset($output['amount']) ? (int) $output['amount'] : 0;
        }

        if ($out > $in) {
            throw new DaemonException("/get_transactions: transaction $hash pays out more than it brings in");
        }

        return $in - $out;
    }






    // -----------------------------------------------------------
    // post
    // -----------------------------------------------------------
    //
    // POSTs the JSON body to the node's $path and returns the
    // HTTP status and the response body, with CONNECT_TIMEOUT
    // to connect and TIMEOUT for the whole call. With a login,
    // curl answers the node's digest challenge. A request that
    // got no HTTP answer at all — nothing listening, a timeout
    // — is a DaemonException naming the address and curl's
    // reason.
    //
    // Used by:
    //   - call, request (above)
    //   - FakeDaemon (tests/Support) — overrides it with a node
    //     in memory
    // -----------------------------------------------------------

    protected function post($path, $body)
    {
        $endpoint = $this->url . $path;
        $curl = curl_init($endpoint);

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
            throw new DaemonException("cannot reach the node at $endpoint: $error");
        }

        return [$status, $response];
    }
}
