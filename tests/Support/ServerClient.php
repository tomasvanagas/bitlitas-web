<?php

// -----------------------------------------------------------
//  [*] ServerClient — an HTTP client for the real nginx
//
//  Talks to the nginx that run.sh starts on port 80 the way
//  the bitlitas-endpoint Caddy does in production: every
//  request carries the public host name, which Caddy passes
//  through, and the X-Forwarded headers a reverse proxy adds
//  — the visitor's address and that the visit came in over
//  HTTPS. Plain curl, since the app's vendor ships no HTTP
//  client and the suite adds none: redirects are never
//  followed, error statuses never throw, and a path goes
//  out exactly as written, dots and all, so a test sees
//  what nginx answered to what it sent.
//
//  Used by:
//    - ServerTestCase (tests/) — one client per test
// -----------------------------------------------------------


namespace Tests\Support;








// -----------------------------------------------------------
// ServerClient
// -----------------------------------------------------------
//
// One request per call; nothing is kept between them.
//
//   get, head — a request for the path
//
// Used by:
//   - ServerTestCase (tests/)
// -----------------------------------------------------------

class ServerClient
{

    // Where run.sh starts nginx inside the container
    private const BASE = 'http://127.0.0.1';

    // What the request carries when it reaches nginx through
    // the endpoint
    public const ENDPOINT_HEADERS = [
        'Host'              => 'bitlitas.lt',
        'X-Forwarded-For'   => '203.0.113.10',
        'X-Forwarded-Host'  => 'bitlitas.lt',
        'X-Forwarded-Proto' => 'https',
    ];






    // -----------------------------------------------------------
    // get
    // -----------------------------------------------------------
    //
    // A GET of the path, with any extra headers, which replace
    // the endpoint's of the same name.
    //
    // Used by:
    //   - the Server suite (Server/)
    // -----------------------------------------------------------

    public function get(string $path, array $headers = []): ServerResponse
    {
        return $this->request('GET', $path, $headers);
    }






    // -----------------------------------------------------------
    // head
    // -----------------------------------------------------------
    //
    // A HEAD of the path: the headers of a GET without its
    // body, for the large downloads.
    //
    // Used by:
    //   - FrontControllerTest (Server/)
    // -----------------------------------------------------------

    public function head(string $path, array $headers = []): ServerResponse
    {
        return $this->request('HEAD', $path, $headers);
    }






    // -----------------------------------------------------------
    // request
    // -----------------------------------------------------------
    //
    // Any request: the endpoint's headers under the extra
    // ones, the response headers collected as they arrive.
    //
    // Used by:
    //   - get, head (above)
    // -----------------------------------------------------------

    private function request(string $method, string $path, array $headers): ServerResponse
    {
        $lines = [];
        foreach (array_merge(self::ENDPOINT_HEADERS, $headers) as $name => $value) {
            $lines[] = "$name: $value";
        }

        $received = [];
        $curl = curl_init(self::BASE . $path);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_NOBODY         => $method === 'HEAD',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $lines,
            CURLOPT_PATH_AS_IS     => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HEADERFUNCTION => function ($curl, string $line) use (&$received): int {
                if (strpos($line, ':') !== false) {
                    list($name, $value) = explode(':', $line, 2);
                    $received[strtolower(trim($name))][] = trim($value);
                }
                return strlen($line);
            },
        ]);
        $body = curl_exec($curl);
        if ($body === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new \RuntimeException("nginx did not answer $method $path: $error");
        }
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        return new ServerResponse($status, $received, (string) $body);
    }
}
