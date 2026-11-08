<?php

namespace App\Services;

/**
 * Client for a Bitlitas full node's (bitlitasd) JSON-RPC interface.
 *
 * It only uses calls that also work on a node started with
 * --restricted-rpc and that answer even while the node doesn't count
 * as synchronized with its peers. (get_last_block_header, for one,
 * answers BUSY then, and a lone node mining its own chain may never
 * count as synchronized.)
 */
class DaemonClient
{
    /** Seconds allowed to connect, and for a whole call */
    const CONNECT_TIMEOUT = 5;
    const TIMEOUT = 30;

    /** @var string */
    private $endpoint;

    /** @var string|null */
    private $login;

    /**
     * @param string      $url    the node's RPC address, e.g. http://bitlitas-exit:18081
     * @param string|null $login  "user:password" when the node runs with --rpc-login
     */
    public function __construct($url, $login = null)
    {
        $this->endpoint = rtrim($url, '/') . '/json_rpc';
        $this->login = $login === '' ? null : $login;
    }

    /**
     * The node's view of the chain: height (the number of blocks, so
     * the top block is height - 1), difficulty (of the next block) and
     * target (seconds per block), among others.
     *
     * @return array
     *
     * @throws DaemonException
     */
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

    /**
     * Headers of blocks $start to $end (both included), oldest first.
     * Each has height, timestamp and reward: the atomic units the
     * block's miner received, new coins plus fees.
     *
     * @param  int $start
     * @param  int $end
     * @return array[]
     *
     * @throws DaemonException
     */
    public function getBlockHeadersRange($start, $end)
    {
        $result = $this->call('get_block_headers_range', [
            'start_height' => (int) $start,
            'end_height' => (int) $end,
        ]);

        if (!isset($result['headers']) || !is_array($result['headers'])) {
            throw new DaemonException('get_block_headers_range: the answer has no headers');
        }

        return $result['headers'];
    }

    /**
     * Make one JSON-RPC call and return its result.
     *
     * @param  string $method
     * @param  array  $params
     * @return array
     *
     * @throws DaemonException  the node is unreachable, refused the call or answered with an error
     */
    public function call($method, array $params = [])
    {
        list($status, $body) = $this->post(json_encode([
            'jsonrpc' => '2.0',
            'id' => '0',
            'method' => $method,
            'params' => (object) $params,
        ]));

        if ($status === 401) {
            throw new DaemonException("$method: the node refused the login (BITLITAS_DAEMON_LOGIN)");
        }

        if ($status !== 200) {
            throw new DaemonException("$method: the node answered HTTP $status");
        }

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

    /**
     * POST a JSON body to the node.
     *
     * @param  string $body
     * @return array  [HTTP status, response body]
     *
     * @throws DaemonException  no HTTP answer came back
     */
    protected function post($body)
    {
        $curl = curl_init($this->endpoint);

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::TIMEOUT,
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
