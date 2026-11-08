<?php

// -----------------------------------------------------------
//  [*] ServerResponse — what Apache answered
//
//  The status, the headers and the body of one answer, with
//  the readers the Server suite needs: a header by name, the
//  cookies with their attributes, the body as JSON.
//
//  Used by:
//    - ServerClient (this folder) — builds one per request
//    - the Server suite (Server/)
// -----------------------------------------------------------


namespace Tests\Support;








// -----------------------------------------------------------
// ServerResponse
// -----------------------------------------------------------
//
// Header names are kept in lower case; a name can carry
// several values (Set-Cookie does).
//
//   header  — the first value of a header, or ''
//   cookies — every cookie set, by name
//   json    — the body, decoded
//
// Used by:
//   - ServerClient (this folder)
//   - the Server suite (Server/)
// -----------------------------------------------------------

class ServerResponse
{

    // The answer as it came
    public $status;
    public $headers;
    public $body;






    // -----------------------------------------------------------
    // __construct
    // -----------------------------------------------------------
    //
    // The status, the headers by lower-case name, and the
    // body.
    //
    // Used by:
    //   - ServerClient::request (this folder)
    // -----------------------------------------------------------

    public function __construct(int $status, array $headers, string $body)
    {
        $this->status = $status;
        $this->headers = $headers;
        $this->body = $body;
    }






    // -----------------------------------------------------------
    // header
    // -----------------------------------------------------------
    //
    // The first value of the header, or '' when the answer
    // has none.
    //
    // Used by:
    //   - the Server suite (Server/)
    // -----------------------------------------------------------

    public function header(string $name): string
    {
        return $this->headers[strtolower($name)][0] ?? '';
    }






    // -----------------------------------------------------------
    // cookies
    // -----------------------------------------------------------
    //
    // Every Set-Cookie, by cookie name: its value and its
    // attributes, attribute names in lower case (a flag such
    // as Secure or HttpOnly maps to true).
    //
    // Used by:
    //   - ProtectionTest, FrontControllerTest (Server/)
    // -----------------------------------------------------------

    public function cookies(): array
    {
        $cookies = [];
        foreach ($this->headers['set-cookie'] ?? [] as $line) {
            $parts = array_map('trim', explode(';', $line));
            list($name, $value) = explode('=', array_shift($parts), 2);
            $attributes = [];
            foreach ($parts as $part) {
                $pair = explode('=', $part, 2);
                $attributes[strtolower($pair[0])] = $pair[1] ?? true;
            }
            $cookies[$name] = ['value' => $value, 'attributes' => $attributes];
        }
        return $cookies;
    }






    // -----------------------------------------------------------
    // json
    // -----------------------------------------------------------
    //
    // The body decoded as JSON, or null when it is none.
    //
    // Used by:
    //   - the Server suite (Server/)
    // -----------------------------------------------------------

    public function json(): ?array
    {
        $json = json_decode($this->body, true);
        return is_array($json) ? $json : null;
    }
}
