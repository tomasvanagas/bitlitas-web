<?php

// -----------------------------------------------------------
//  [*] NodeEmulator — answers JSON-RPC the way bitlitasd does
//
//  The RPC side of a bitlitasd v0.18 node, the one the
//  stack runs, as its core_rpc_server.cpp answers: get_info
//  and get_block_headers_range with the fields of v0.18's
//  answers and the headers of Chain, get_block with a
//  block's transactions, /get_transactions with them decoded
//  as JSON, the node's own error for a range or a block past
//  the top, and "Method not found" for any other method. One
//  state array decides everything, so a test can ask for any
//  answer a node could give: a busy status, a restricted
//  node's caps of 1000 headers and 100 transactions a call,
//  a range that fails or comes back damaged on its nth call,
//  blocks whose transactions paid fees — before RingCT or
//  with it — an answer without headers or without some info,
//  a chain that grows after every get_info, or — before any
//  RPC — an HTTP error or a body that is no JSON. The
//  counters in the state record how many calls of each
//  method it has answered.
//
//  Used by:
//    - FakeDaemon (this folder) — in memory, for the Feature
//      suite
//    - tests/container/fake-node.php — over HTTP, for
//      DaemonClientTest and the Server suite
// -----------------------------------------------------------


namespace Tests\Support;








// -----------------------------------------------------------
// NodeEmulator
// -----------------------------------------------------------
//
// The state is public: the HTTP router stores it back after
// every request, and a test reads its counters.
//
//   answer       — the HTTP status and body for a request
//   info         — get_info's result
//   range        — get_block_headers_range's result, or its
//                  error
//   block        — get_block's result, or its error
//   transactions — /get_transactions' answer
//   transaction  — one transaction, as as_json shows it
//   withFees     — adds the fees a block's transactions paid
//   feesOf       — the fee of each transaction of a block
//   damage       — breaks headers the way a corrupt mode asks
//
// Used by:
//   - FakeDaemon (this folder)
//   - tests/container/fake-node.php
// -----------------------------------------------------------

class NodeEmulator
{

    // Every state key and its value when a test sets none.
    // height counts the blocks, as get_info's height does; the
    // top block is height − 1. fees maps a height to the fee,
    // in atomic units, of its block's one transaction, or to a
    // list of fees, one per transaction; rct writes those
    // transactions the RingCT way. range_cap marks a
    // restricted node
    public const DEFAULTS = [
        'height'       => 2500,
        'difficulty'   => 1160,
        'target'       => 60,
        'fees'         => [],
        'rct'          => false,
        'status'       => 'OK',
        'range_cap'    => null,
        'omit_info'    => [],
        'no_headers'   => false,
        'corrupt'      => null,
        'corrupt_call' => null,
        'error_call'   => null,
        'grow'         => 0,
        'http_status'  => null,
        'raw_body'     => null,
        'big_numbers'  => false,
        'login'        => null,
        'calls'        => ['get_info' => 0, 'get_block_headers_range' => 0, 'get_block' => 0, 'get_transactions' => 0],
    ];

    // The most transactions a restricted node gives in one
    // /get_transactions call
    private const RESTRICTED_TRANSACTIONS = 100;

    // Stands in a JSON answer for a number above PHP's
    // integers, which json_encode cannot write
    private const BIG = '"__beyond_php_int_max__"';

    // The state this node answers from
    public $state;






    // -----------------------------------------------------------
    // __construct
    // -----------------------------------------------------------
    //
    // The state a test asked for, over the defaults.
    //
    // Used by:
    //   - FakeDaemon (this folder)
    //   - tests/container/fake-node.php
    // -----------------------------------------------------------

    public function __construct(array $state = [])
    {
        $this->state = array_replace(self::DEFAULTS, $state);
        $this->state['calls'] = array_replace(self::DEFAULTS['calls'], $this->state['calls']);
    }






    // -----------------------------------------------------------
    // answer
    // -----------------------------------------------------------
    //
    // [HTTP status, body] for a request body POSTed to $path:
    // /get_transactions answers in plain JSON, every other
    // path as JSON-RPC. An HTTP status or a raw body in the
    // state comes back as it is, before the request is even
    // read.
    //
    // Used by:
    //   - FakeDaemon::post (this folder)
    //   - tests/container/fake-node.php
    // -----------------------------------------------------------

    public function answer(string $body, string $path = '/json_rpc'): array
    {
        if ($this->state['http_status'] !== null || $this->state['raw_body'] !== null) {
            return [$this->state['http_status'] ?? 200, (string) $this->state['raw_body']];
        }

        if ($path === '/get_transactions') {
            $this->state['calls']['get_transactions']++;
            return [200, json_encode($this->transactions(json_decode($body, true) ?: []), JSON_PRETTY_PRINT)];
        }

        $request = json_decode($body, true);
        $id = $request['id'] ?? null;
        $params = $request['params'] ?? [];

        switch ($request['method'] ?? null) {
            case 'get_info':
                $this->state['calls']['get_info']++;
                $outcome = ['result' => $this->info()];
                $this->state['height'] += $this->state['grow'];
                break;
            case 'get_block_headers_range':
                $this->state['calls']['get_block_headers_range']++;
                $outcome = $this->range(is_array($params) ? $params : []);
                break;
            case 'get_block':
                $this->state['calls']['get_block']++;
                $outcome = $this->block(is_array($params) ? $params : []);
                break;
            default:
                $outcome = ['error' => ['code' => -32601, 'message' => 'Method not found']];
        }

        $json = json_encode(['id' => $id, 'jsonrpc' => '2.0'] + $outcome, JSON_PRETTY_PRINT);
        return [200, str_replace(self::BIG, '18446744073709551615', $json)];
    }






    // -----------------------------------------------------------
    // info
    // -----------------------------------------------------------
    //
    // get_info's result with the fields of v0.18's answer,
    // minus the fields omit_info names. With big_numbers,
    // cumulative_difficulty is 2^64 − 1, a number beyond PHP's
    // integers.
    //
    // Used by:
    //   - answer (above)
    // -----------------------------------------------------------

    private function info(): array
    {
        $height = $this->state['height'];
        $cumulative = 1 + Chain::DIFFICULTY * ($height - 1);
        $info = [
            'adjusted_time'               => 1794113261,
            'alt_blocks_count'            => 0,
            'block_size_limit'            => 600000,
            'block_size_median'           => 300000,
            'block_weight_limit'          => 600000,
            'block_weight_median'         => 300000,
            'bootstrap_daemon_address'    => '',
            'busy_syncing'                => false,
            'credits'                     => 0,
            'cumulative_difficulty'       => $this->state['big_numbers'] ? trim(self::BIG, '"') : $cumulative,
            'cumulative_difficulty_top64' => 0,
            'database_size'               => 1073741824,
            'difficulty'                  => $this->state['difficulty'],
            'difficulty_top64'            => 0,
            'free_space'                  => 153006481408,
            'grey_peerlist_size'          => 0,
            'height'                      => $height,
            'height_without_bootstrap'    => $height,
            'incoming_connections_count'  => 2,
            'mainnet'                     => true,
            'nettype'                     => 'mainnet',
            'offline'                     => false,
            'outgoing_connections_count'  => 0,
            'restricted'                  => $this->state['range_cap'] !== null,
            'rpc_connections_count'       => 1,
            'stagenet'                    => false,
            'start_time'                  => 1794106061,
            'status'                      => $this->state['status'],
            'synchronized'                => true,
            'target'                      => $this->state['target'],
            'target_height'               => 0,
            'testnet'                     => false,
            'top_block_hash'              => Chain::hash($height - 1),
            'top_hash'                    => '',
            'tx_count'                    => 0,
            'tx_pool_size'                => 0,
            'untrusted'                   => false,
            'update_available'            => false,
            'version'                     => '0.18.3.1-release',
            'was_bootstrap_ever_used'     => false,
            'white_peerlist_size'         => 4,
            'wide_cumulative_difficulty'  => '0x' . dechex($cumulative),
            'wide_difficulty'             => '0x' . dechex((int) $this->state['difficulty']),
        ];
        return array_diff_key($info, array_flip($this->state['omit_info']));
    }






    // -----------------------------------------------------------
    // range
    // -----------------------------------------------------------
    //
    // The headers of start_height to end_height, or the error
    // the node would give: for heights out of the chain, for
    // more than range_cap headers, and a generic one on the
    // call error_call names.
    //
    // Used by:
    //   - answer (above)
    // -----------------------------------------------------------

    private function range(array $params): array
    {
        $call = $this->state['calls']['get_block_headers_range'];
        $start = $params['start_height'] ?? null;
        $end = $params['end_height'] ?? null;
        $height = $this->state['height'];

        if (!is_int($start) || !is_int($end) || $end >= $height || $start > $end) {
            return ['error' => ['code' => -2, 'message' => 'Invalid start/end heights.']];
        }
        if ($this->state['range_cap'] !== null && $end - $start > $this->state['range_cap']) {
            return ['error' => ['code' => -2, 'message' => 'Too many block headers requested.']];
        }
        if ($this->state['error_call'] === $call) {
            return ['error' => ['code' => -5, 'message' => "Internal error: can't get block by height. Height = $start."]];
        }

        $result = ['headers' => $this->withFees(Chain::headers($start, $end, $height - 1)), 'status' => $this->state['status']];
        if ($this->state['corrupt'] !== null && in_array($this->state['corrupt_call'], [null, $call], true)) {
            $result['headers'] = $this->damage($result['headers'], $this->state['corrupt']);
        }
        if ($this->state['no_headers']) {
            unset($result['headers']);
        }
        return ['result' => $result];
    }






    // -----------------------------------------------------------
    // block
    // -----------------------------------------------------------
    //
    // get_block's result for the block at height: its header
    // and the hashes of its transactions, which the node
    // leaves out when there are none; or the node's error for
    // a height past the top.
    //
    // Used by:
    //   - answer (above)
    // -----------------------------------------------------------

    private function block(array $params): array
    {
        $height = $params['height'] ?? null;
        $top = $this->state['height'] - 1;

        if (!is_int($height) || $height > $top) {
            return ['error' => ['code' => -2, 'message' => "Requested block height: $height greater than current top block height: $top"]];
        }

        $header = $this->withFees([Chain::header($height, $top)])[0];
        $result = [
            'blob'          => '',
            'block_header'  => $header,
            'json'          => '{}',
            'miner_tx_hash' => $header['miner_tx_hash'],
            'status'        => $this->state['status'],
            'untrusted'     => false,
        ];

        $hashes = [];
        foreach (array_keys($this->feesOf($height)) as $index) {
            $hashes[] = Chain::txHash($height, $index);
        }
        if ($hashes !== []) {
            $result['tx_hashes'] = $hashes;
        }
        return ['result' => $result];
    }






    // -----------------------------------------------------------
    // transactions
    // -----------------------------------------------------------
    //
    // /get_transactions' answer for the hashes asked for: each
    // known transaction with its as_json when the request asks
    // to decode, an unknown one in missed_tx. A restricted node
    // — range_cap set — refuses more than
    // RESTRICTED_TRANSACTIONS at once, in its status, the way
    // v0.18 does.
    //
    // Used by:
    //   - answer (above)
    // -----------------------------------------------------------

    private function transactions(array $request): array
    {
        $hashes = $request['txs_hashes'] ?? [];

        if ($this->state['range_cap'] !== null && count($hashes) > self::RESTRICTED_TRANSACTIONS) {
            return ['status' => 'Too many transactions requested in restricted mode', 'untrusted' => false];
        }

        $known = [];
        foreach (array_keys($this->state['fees']) as $height) {
            if ((int) $height < $this->state['height']) {
                foreach ($this->feesOf((int) $height) as $index => $fee) {
                    $known[Chain::txHash((int) $height, $index)] = [(int) $height, $fee];
                }
            }
        }

        $txs = [];
        $missed = [];
        foreach ($hashes as $hash) {
            if (!isset($known[$hash])) {
                $missed[] = $hash;
                continue;
            }
            [$height, $fee] = $known[$hash];
            $txs[] = [
                'as_hex'          => '',
                'as_json'         => empty($request['decode_as_json']) ? '' : json_encode($this->transaction($hash, $fee)),
                'block_height'    => $height,
                'block_timestamp' => Chain::timestamp($height),
                'confirmations'   => $this->state['height'] - $height,
                'in_pool'         => false,
                'output_indices'  => [0],
                'relayed'         => false,
                'tx_hash'         => $hash,
            ];
        }

        $answer = ['credits' => 0, 'status' => 'OK', 'top_hash' => '', 'txs' => $txs, 'untrusted' => false];
        if ($missed !== []) {
            $answer['missed_tx'] = $missed;
        }
        return $answer;
    }






    // -----------------------------------------------------------
    // transaction
    // -----------------------------------------------------------
    //
    // A transaction paying $fee, decoded the way as_json shows
    // it: from before RingCT, with its amounts in the clear —
    // its one input bringing a LIT more than its one output
    // pays — or, with rct, with hidden amounts and the fee in
    // rct_signatures.txnFee.
    //
    // Used by:
    //   - transactions (above)
    // -----------------------------------------------------------

    private function transaction(string $hash, int $fee): array
    {
        $input = ['key' => ['amount' => 0, 'key_offsets' => [1], 'k_image' => $hash]];
        $output = ['amount' => 0, 'target' => ['key' => $hash]];

        if ($this->state['rct']) {
            return ['version' => 2, 'unlock_time' => 0, 'vin' => [$input], 'vout' => [$output, $output], 'extra' => [1],
                    'rct_signatures' => ['type' => 1, 'txnFee' => $fee, 'ecdhInfo' => [], 'outPk' => []]];
        }

        $input['key']['amount'] = Chain::ATOMIC_UNITS + $fee;
        $output['amount'] = Chain::ATOMIC_UNITS;
        return ['version' => 1, 'unlock_time' => 0, 'vin' => [$input], 'vout' => [$output], 'extra' => [1], 'signatures' => []];
    }






    // -----------------------------------------------------------
    // withFees
    // -----------------------------------------------------------
    //
    // The headers with the fees of the state added: a block
    // whose transactions paid fees holds that many
    // transactions, and its reward is the new coins and the
    // fees together, the way the node reports what the miner
    // took.
    //
    // Used by:
    //   - range, block (above)
    // -----------------------------------------------------------

    private function withFees(array $headers): array
    {
        foreach ($headers as $i => $header) {
            $fees = $this->feesOf($header['height']);
            $headers[$i]['reward'] += array_sum($fees);
            $headers[$i]['num_txes'] = count($fees);
        }
        return $headers;
    }






    // -----------------------------------------------------------
    // feesOf
    // -----------------------------------------------------------
    //
    // The fee of each transaction of block $height, by the
    // state's fees: none for a block the state leaves out.
    //
    // Used by:
    //   - withFees (above), block, transactions (below)
    // -----------------------------------------------------------

    private function feesOf(int $height): array
    {
        $fees = $this->state['fees'][$height] ?? [];
        return array_values(is_array($fees) ? $fees : [$fees]);
    }






    // -----------------------------------------------------------
    // damage
    // -----------------------------------------------------------
    //
    // The headers broken one way: a gap after the first, the
    // first with the wrong height, the last one missing, one
    // past the end added, the first without its reward, time,
    // height or count of transactions, or every block with
    // transactions counting one more than get_block lists.
    //
    // Used by:
    //   - range (above)
    // -----------------------------------------------------------

    private function damage(array $headers, string $mode): array
    {
        switch ($mode) {
            case 'gap':
                array_splice($headers, 1, 1);
                break;
            case 'wrong-height':
                $headers[0]['height']++;
                break;
            case 'too-few':
                array_pop($headers);
                break;
            case 'too-many':
                $last = end($headers);
                $headers[] = Chain::header($last['height'] + 1, $last['height'] + 1);
                break;
            case 'no-reward':
                unset($headers[0]['reward']);
                break;
            case 'no-timestamp':
                unset($headers[0]['timestamp']);
                break;
            case 'no-height':
                unset($headers[0]['height']);
                break;
            case 'no-num-txes':
                unset($headers[0]['num_txes']);
                break;
            case 'extra-tx':
                foreach ($headers as $i => $header) {
                    if ($header['num_txes'] > 0) {
                        $headers[$i]['num_txes']++;
                    }
                }
                break;
        }
        return $headers;
    }
}
