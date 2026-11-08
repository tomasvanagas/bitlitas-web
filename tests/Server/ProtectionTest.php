<?php

// -----------------------------------------------------------
//  [*] ProtectionTest — what the web server never gives out
//
//  Apache's document root is public/, so the app's code,
//  its .env with the key, composer's files, Apache's own
//  configuration, the log and the git metadata are outside
//  what it serves: a request for them reaches Laravel and
//  gets its 404, and a path that climbs out of the root is
//  refused. PHP runs only from .php files — a picture asked
//  for as a script is never run — and Apache names no
//  version.
//
//  Used by:
//    - PHPUnit — the Server suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Server;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\ServerTestCase;








// -----------------------------------------------------------
// ProtectionTest
// -----------------------------------------------------------
//
// Plain requests through ServerClient; paths go out exactly
// as written.
//
// Used by:
//   - PHPUnit — the Server suite (runTests.sh)
// -----------------------------------------------------------

class ProtectionTest extends ServerTestCase
{






    // -----------------------------------------------------------
    // testFilesOutsidePublicAreNeverServed
    // -----------------------------------------------------------
    //
    // Each is refused — 404 or, for a path climbing out of the
    // root, 400 — and the answer holds nothing of the file.
    //
    // Used by:
    //   - PHPUnit — the Server suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('privateFiles')]
    public function testFilesOutsidePublicAreNeverServed(string $path, string $inside): void
    {
        $response = $this->http->get($path);

        $this->assertContains($response->status, [400, 404], "$path answered {$response->status}");
        $this->assertStringNotContainsString($inside, $response->body);
    }






    // -----------------------------------------------------------
    // privateFiles
    // -----------------------------------------------------------
    //
    // Paths of files outside public/, each with a string only
    // that file holds.
    //
    // Used by:
    //   - testFilesOutsidePublicAreNeverServed (above)
    // -----------------------------------------------------------

    public static function privateFiles(): array
    {
        return [
            'the environment'        => ['/.env', 'APP_KEY'],
            'the environment, above' => ['/../.env', 'APP_KEY'],
            'the environment, coded' => ['/%2e%2e/.env', 'APP_KEY'],
            'composer.json'          => ['/composer.json', 'laravel/framework'],
            'composer.lock'          => ['/composer.lock', 'content-hash'],
            'artisan'                => ['/artisan', 'LARAVEL_START'],
            'server.php'             => ['/server.php', 'REQUEST_URI'],
            'the configuration'      => ['/config/app.php', 'APP_DEBUG'],
            'the node client'        => ['/app/Services/DaemonClient.php', 'json_rpc'],
            'the virtual host'       => ['/apache/000-default.conf', 'RemoteIPHeader'],
            'the log'                => ['/storage/logs/laravel.log', 'WARNING'],
            'the git metadata'       => ['/.git/config', '[core]'],
        ];
    }






    // -----------------------------------------------------------
    // testPictureAskedForAsAScriptIsNeverRun
    // -----------------------------------------------------------
    //
    // PHP runs only for a file whose name ends in .php: the
    // logo with /x.php behind it is the logo with a path
    // after it, which Apache refuses rather than runs.
    //
    // Used by:
    //   - PHPUnit — the Server suite (runTests.sh)
    // -----------------------------------------------------------

    public function testPictureAskedForAsAScriptIsNeverRun(): void
    {
        $response = $this->http->get('/img/logo.png/x.php');

        $this->assertContains($response->status, [403, 404]);
        $this->assertStringNotContainsString("\x89PNG", $response->body);
    }






    // -----------------------------------------------------------
    // testApacheNamesNoVersion
    // -----------------------------------------------------------
    //
    // The Server header says Apache and nothing more, and
    // Apache's own error pages sign with no version either.
    //
    // Used by:
    //   - PHPUnit — the Server suite (runTests.sh)
    // -----------------------------------------------------------

    public function testApacheNamesNoVersion(): void
    {
        $this->assertSame('Apache', $this->http->head('/css/main.css')->header('Server'));
        $this->assertStringNotContainsString('Apache/', $this->http->get('/css/')->body);
    }
}
