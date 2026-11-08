<?php

// -----------------------------------------------------------
//  [*] NodeEmulator — answers JSON-RPC the way bitlitasd does
//
//  The JSON-RPC side of a bitlitasd v0.11 node, as its
//  core_rpc_server.cpp answers: get_info with the fields a
//  real node reported on 2026-11-08, get_block_headers_range
//  with the headers of Chain, the node's own error for a
//  range past the top ("Invalid start/end heights.", code
//  −2), and "Method not found" for any other method. One
//  state array decides everything, so a test can ask for any
//  answer a node could give: a busy status, a range cap of
//  1000 like a restricted v0.18 node, a range that fails or
//  comes back damaged on its nth call, an answer without
//  headers or without some info, a chain that grows after
//  every get_info, or — before any JSON-RPC — an HTTP error
//  or a body that is no JSON. The counters in the state
//  record how many calls of each method it has answered.
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
//   answer — the HTTP status and body for a request body
//   info   — get_info's result
//   range  — get_block_headers_range's result, or its error
//   damage — breaks headers the way a corrupt mode asks
//
// Used by:
//   - FakeDaemon (this folder)
//   - tests/container/fake-node.php
// -----------------------------------------------------------

class NodeEmulator
{

    // Every state key and its value when a test sets none.
    // height counts the blocks, as get_info's height does; the
    // top block is height − 1
    public const DEFAULTS = [
        'height'       => 2500,
        'difficulty'   => 1160,
        'target'       => 60,
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
        'calls'        => ['get_info' => 0, 'get_block_headers_range' => 0],
    ];

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
    // [HTTP status, body] for a JSON-RPC request body. An HTTP
    // status or a raw body in the state comes back as it is,
    // before the request is even read.
    //
    // Used by:
    //   - FakeDaemon::post (this folder)
    //   - tests/container/fake-node.php
    // -----------------------------------------------------------

    public function answer(string $body): array
    {
        if ($this->state['http_status'] !== null || $this->state['raw_body'] !== null) {
            return [$this->state['http_status'] ?? 200, (string) $this->state['raw_body']];
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
    // get_info's result, minus the fields omit_info names. With
    // big_numbers, cumulative_difficulty is 2^64 − 1, a number
    // beyond PHP's integers.
    //
    // Used by:
    //   - answer (above)
    // -----------------------------------------------------------

    private function info(): array
    {
        $height = $this->state['height'];
        $info = [
            'alt_blocks_count'           => 0,
            'block_size_limit'           => 40000,
            'block_size_median'          => 277,
            'cumulative_difficulty'      => $this->state['big_numbers'] ? trim(self::BIG, '"') : 1 + Chain::DIFFICULTY * ($height - 1),
            'difficulty'                 => $this->state['difficulty'],
            'free_space'                 => 153006481408,
            'grey_peerlist_size'         => 0,
            'height'                     => $height,
            'incoming_connections_count' => 2,
            'offline'                    => false,
            'outgoing_connections_count' => 0,
            'rpc_connections_count'      => 1,
            'start_time'                 => 1794106061,
            'status'                     => $this->state['status'],
            'target'                     => $this->state['target'],
            'target_height'              => 0,
            'testnet'                    => false,
            'top_block_hash'             => Chain::hash($height - 1),
            'tx_count'                   => 0,
            'tx_pool_size'               => 0,
            'white_peerlist_size'        => 4,
        ];
        return array_diff_key($info, array_flip($this->state['omit_info']));
    }






    // -----------------------------------------------------------
    // range
    // -----------------------------------------------------------
    //
    // The headers of start_height to end_height, or the error
    // the node would give: v0.11's for heights out of the
    // chain, v0.18's for more than range_cap headers, a
    // generic one on the call error_call names.
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

        $result = ['headers' => Chain::headers($start, $end, $height - 1), 'status' => $this->state['status']];
        if ($this->state['corrupt'] !== null && in_array($this->state['corrupt_call'], [null, $call], true)) {
            $result['headers'] = $this->damage($result['headers'], $this->state['corrupt']);
        }
        if ($this->state['no_headers']) {
            unset($result['headers']);
        }
        return ['result' => $result];
    }






    // -----------------------------------------------------------
    // damage
    // -----------------------------------------------------------
    //
    // The headers broken one way: a gap after the first, the
    // first with the wrong height, the last one missing, one
    // past the end added, or the first without its reward,
    // time or height.
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
        }
        return $headers;
    }
}
