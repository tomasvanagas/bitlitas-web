<?php

// -----------------------------------------------------------
//  [*] fake-node.php — the full node's stand-in, over HTTP
//
//  The router script PHP's built-in server runs on
//  127.0.0.1:18081, where BITLITAS_DAEMON_URL points in the
//  test container: the address bitlitas-exit forwards to
//  the real node in production. POST /json_rpc is answered
//  by NodeEmulator from the state the tests wrote to
//  /tmp/fake-node/state.json, and the state goes back to
//  the file afterwards — the counters of the calls, and the
//  height when the chain grows on every get_info. With a
//  login in the state, every request first has to answer
//  the HTTP digest challenge, as with bitlitasd's
//  --rpc-login. Every request is appended to
//  /tmp/fake-node/requests.jsonl, which the tests read back.
//  The built-in server answers one request at a time, so
//  reading and writing the state never overlap.
//
//  Used by:
//    - run.sh (this folder) — php -S starts it
//    - FakeNodeServer (Support/) — writes its state, reads
//      its requests
// -----------------------------------------------------------


require __DIR__ . '/../Support/Chain.php';
require __DIR__ . '/../Support/NodeEmulator.php';

use Tests\Support\NodeEmulator;

// Where the tests leave the node's state and read its request
// log, and the realm of the digest challenge
const STATE = '/tmp/fake-node/state.json';
const REQUESTS = '/tmp/fake-node/requests.jsonl';
const REALM = 'bitlitas-rpc';


// STEP 1: the state as the tests left it, the request as it
// came in, and its line in the request log
// =========================================================
$state = json_decode((string) @file_get_contents(STATE), true) ?: [];
$body = (string) file_get_contents('php://input');
$authorization = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION']
    : (isset($_SERVER['PHP_AUTH_DIGEST']) ? 'Digest ' . $_SERVER['PHP_AUTH_DIGEST'] : null);

file_put_contents(REQUESTS, json_encode([
    'http_method'   => $_SERVER['REQUEST_METHOD'],
    'path'          => parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),
    'content_type'  => isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : null,
    'authorization' => $authorization === null ? null : strtok($authorization, ' '),
    'raw'           => $body,
    'body'          => json_decode($body, true),
]) . "\n", FILE_APPEND);


// STEP 2: anything but POST /json_rpc is not found, as on
// the real node
// =======================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) !== '/json_rpc') {
    http_response_code(404);
    return true;
}


// STEP 3: with a login in the state, the request must answer
// the digest challenge first; the nonce is fixed, which is
// all a client needs to answer it
// ==========================================================
if (!empty($state['login']) && !digestAnswered($authorization, $state['login'])) {
    http_response_code(401);
    header('WWW-Authenticate: Digest realm="' . REALM . '", qop="auth", algorithm=MD5, nonce="' . md5(REALM . $state['login']) . '"');
    return true;
}


// STEP 4: the emulator's answer, and its state stored for
// the next request
// =======================================================
$node = new NodeEmulator($state);
list($status, $answer) = $node->answer($body);
file_put_contents(STATE, json_encode($node->state));

http_response_code($status);
header('Content-Type: application/json');
echo $answer;
return true;








// -----------------------------------------------------------
// digestAnswered
// -----------------------------------------------------------
//
// Whether the Authorization header answers the challenge
// for "user:password" (RFC 2617, MD5 with qop auth): the
// right user, realm and nonce, and the response hash the
// password gives.
//
// Used by:
//   - the answer (above)
// -----------------------------------------------------------

function digestAnswered($authorization, $login)
{
    if ($authorization === null || stripos($authorization, 'Digest ') !== 0) {
        return false;
    }
    preg_match_all('/(\w+)=(?:"([^"]*)"|([^\s,]+))/', substr($authorization, 7), $pairs, PREG_SET_ORDER);
    $digest = [];
    foreach ($pairs as $pair) {
        $digest[$pair[1]] = $pair[2] !== '' ? $pair[2] : (isset($pair[3]) ? $pair[3] : '');
    }
    list($user, $password) = explode(':', $login, 2);
    foreach (['username', 'realm', 'nonce', 'uri', 'response', 'nc', 'cnonce', 'qop'] as $field) {
        if (!isset($digest[$field])) {
            return false;
        }
    }
    $expected = md5(md5("$user:" . REALM . ":$password") . ":{$digest['nonce']}:{$digest['nc']}:{$digest['cnonce']}:{$digest['qop']}:" . md5("POST:{$digest['uri']}"));

    return $digest['username'] === $user
        && $digest['realm'] === REALM
        && $digest['nonce'] === md5(REALM . $login)
        && hash_equals($expected, $digest['response']);
}
