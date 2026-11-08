<?php

// -----------------------------------------------------------
//  [*] ProtectionTest — what the web server never gives out
//
//  nginx's root is public/, so the app's code, its .env with
//  the key, composer's files, the log and the git metadata
//  are outside what it serves: a request for them reaches
//  Laravel and gets its 404, or none at all, a path that
//  climbs out of the root is refused. PHP runs only from
//  .php files — a picture asked for as a script is never
//  run — and nginx names no version.
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
            'the log'                => ['/storage/logs/laravel.log', 'WARNING'],
            'the git metadata'       => ['/.git/config', '[core]'],
        ];
    }






    // -----------------------------------------------------------
    // testPictureAskedForAsAScriptIsNeverRun
    // -----------------------------------------------------------
    //
    // A path ending in .php is sent to php-fpm, which runs no
    // file but a .php one: the logo with /x.php behind it is
    // refused, not executed.
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
    // testNginxNamesNoVersion
    // -----------------------------------------------------------
    //
    // The Server header says nginx and nothing more.
    //
    // Used by:
    //   - PHPUnit — the Server suite (runTests.sh)
    // -----------------------------------------------------------

    public function testNginxNamesNoVersion(): void
    {
        $this->assertSame('nginx', $this->http->head('/css/main.css')->header('Server'));
    }
}
