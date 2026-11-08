<?php

// -----------------------------------------------------------
//  [*] ClientAddressTest — who the visitor is, behind proxies
//
//  Apache runs deep inside the infrastructure, behind
//  proxies of every kind of address, and trusts every one
//  of them to name the visitor: mod_remoteip takes the first
//  address of X-Forwarded-For as the visitor, whatever peer
//  sends the header and however many proxies, private or
//  public, it passed on the way. The access log records
//  that address, and Laravel, which finds the header taken,
//  sees the same one — the /blokai throttle counts by it.
//
//  Used by:
//    - PHPUnit — the Server suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Server;

use Tests\ServerTestCase;
use Tests\Support\FakeNodeServer;








// -----------------------------------------------------------
// ClientAddressTest
// -----------------------------------------------------------
//
// Requests through ServerClient with X-Forwarded-For
// chains; the access log is read back.
//
//   loggedAddress — the address the access log gives a visit
//
// Used by:
//   - PHPUnit — the Server suite (runTests.sh)
// -----------------------------------------------------------

class ClientAddressTest extends ServerTestCase
{

    // Where Apache writes its access log, a tmpfs here
    private const ACCESS_LOG = '/var/www/logs/access.log';

    // A visitor behind three proxies, each of which added the
    // address it took the request from: a private one, a
    // public one, and one on the stack's Docker networks
    private const CHAIN = '198.51.100.7, 10.1.2.3, 203.0.113.50, 172.19.0.4';






    // -----------------------------------------------------------
    // testAccessLogNamesTheFirstAddressOfTheChain
    // -----------------------------------------------------------
    //
    // Every hop is trusted, private or public alike, so the
    // access log names the chain's first address, the
    // visitor, and none of the proxies.
    //
    // Used by:
    //   - PHPUnit — the Server suite (runTests.sh)
    // -----------------------------------------------------------

    public function testAccessLogNamesTheFirstAddressOfTheChain(): void
    {
        $this->assertSame('198.51.100.7', $this->loggedAddress(self::CHAIN));
    }






    // -----------------------------------------------------------
    // testLoneAddressNamesTheVisitor
    // -----------------------------------------------------------
    //
    // A header of one address — what the endpoint passes on
    // from the proxy in front of it — names the visitor,
    // though the peer that sent it, 127.0.0.1 here, is no
    // address of the stack's networks.
    //
    // Used by:
    //   - PHPUnit — the Server suite (runTests.sh)
    // -----------------------------------------------------------

    public function testLoneAddressNamesTheVisitor(): void
    {
        $this->assertSame('203.0.113.10', $this->loggedAddress('203.0.113.10'));
    }






    // -----------------------------------------------------------
    // testLaravelCountsTheSameVisitor
    // -----------------------------------------------------------
    //
    // The /blokai throttle counts the chain's first address:
    // ten visits through one chain of proxies use up the
    // visitor's allowance for an eleventh through another
    // chain, while the last proxy of the first chain, asking
    // for itself, still gets in.
    //
    // Used by:
    //   - PHPUnit — the Server suite (runTests.sh)
    // -----------------------------------------------------------

    public function testLaravelCountsTheSameVisitor(): void
    {
        FakeNodeServer::configure(['height' => 3]);

        for ($visit = 1; $visit <= 10; $visit++) {
            $this->assertSame(200, $this->http->get('/blokai', ['X-Forwarded-For' => self::CHAIN])->status);
        }

        $this->assertSame(429, $this->http->get('/blokai', ['X-Forwarded-For' => '198.51.100.7, 192.168.5.5'])->status);
        $this->assertSame(200, $this->http->get('/blokai', ['X-Forwarded-For' => '172.19.0.4'])->status);
    }






    // -----------------------------------------------------------
    // loggedAddress
    // -----------------------------------------------------------
    //
    // Sends a visit with the X-Forwarded-For given, marked by a
    // query string no other visit carries, and returns the
    // address the access log's line for it starts with.
    // Apache logs a visit as it finishes it, so the log is
    // read again for up to two seconds.
    //
    // Used by:
    //   - the tests above
    // -----------------------------------------------------------

    private function loggedAddress(string $forwardedFor): string
    {
        $mark = 'client-address-' . bin2hex(random_bytes(6));
        $this->http->get("/robots.txt?$mark", ['X-Forwarded-For' => $forwardedFor]);

        for ($try = 0; $try < 40; $try++) {
            clearstatcache();
            foreach (is_file(self::ACCESS_LOG) ? file(self::ACCESS_LOG, FILE_IGNORE_NEW_LINES) : [] as $line) {
                if (strpos($line, $mark) !== false) {
                    return strtok($line, ' ');
                }
            }
            usleep(50000);
        }
        $this->fail("the access log has no line for $mark");
    }
}
