<?php

// -----------------------------------------------------------
//  [*] ServerDefectsTest — what the answers still give away
//
//  The pinned defects of the web server (tests/README.md):
//  each test states the wanted behaviour and stays
//  incomplete while the defect reproduces. Without a
//  php.ini, PHP's expose_php is on and every answer of the
//  app names PHP's exact version; and the session and CSRF
//  cookies Laravel sets on every page are not marked Secure,
//  though the site is served over HTTPS only.
//
//  Used by:
//    - PHPUnit — the Server suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Server;

use Tests\ServerTestCase;








// -----------------------------------------------------------
// ServerDefectsTest
// -----------------------------------------------------------
//
// One pin per test, through pinDefect.
//
// Used by:
//   - PHPUnit — the Server suite (runTests.sh)
// -----------------------------------------------------------

class ServerDefectsTest extends ServerTestCase
{






    // -----------------------------------------------------------
    // testAnswersDoNotNamePhp
    // -----------------------------------------------------------
    //
    // [expose-php] The app's answers carry no X-Powered-By.
    // PHP adds "X-Powered-By: PHP/7.4.33" to every one,
    // naming a release with known flaws.
    //
    // Used by:
    //   - PHPUnit — the Server suite (runTests.sh)
    // -----------------------------------------------------------

    public function testAnswersDoNotNamePhp(): void
    {
        $this->world->seed();

        $this->pinDefect('expose-php', function () {
            $poweredBy = $this->http->get('/')->header('X-Powered-By');
            $this->assertSame('', $poweredBy, "the home page carries X-Powered-By: $poweredBy");
        });
    }






    // -----------------------------------------------------------
    // testCookiesAreSecure
    // -----------------------------------------------------------
    //
    // [session-cookie-secure] Every cookie the page sets is
    // Secure, so a browser never sends it over plain HTTP.
    // SESSION_SECURE_COOKIE is unset and config/session.php
    // defaults it to false.
    //
    // Used by:
    //   - PHPUnit — the Server suite (runTests.sh)
    // -----------------------------------------------------------

    public function testCookiesAreSecure(): void
    {
        $this->world->seed();

        $this->pinDefect('session-cookie-secure', function () {
            $cookies = $this->http->get('/')->cookies();
            $this->assertNotEmpty($cookies);
            foreach ($cookies as $name => $cookie) {
                $this->assertArrayHasKey('secure', $cookie['attributes'], "$name is not Secure");
            }
        });
    }
}
