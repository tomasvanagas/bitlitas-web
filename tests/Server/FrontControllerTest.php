<?php

// -----------------------------------------------------------
//  [*] FrontControllerTest — how nginx hands requests over
//
//  The routing half of the web server: a path that is a file
//  of public/ is served by nginx itself, with its type and
//  without passing through Laravel; any other path goes to
//  index.php, and Laravel answers one it has no route for
//  with its 404 page. A folder is never listed.
//
//  Used by:
//    - PHPUnit — the Server suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Server;

use Tests\ServerTestCase;








// -----------------------------------------------------------
// FrontControllerTest
// -----------------------------------------------------------
//
// Plain requests through ServerClient, with the headers the
// endpoint adds.
//
// Used by:
//   - PHPUnit — the Server suite (runTests.sh)
// -----------------------------------------------------------

class FrontControllerTest extends ServerTestCase
{






    // -----------------------------------------------------------
    // testHomePageIsServedByTheApp
    // -----------------------------------------------------------
    //
    // The root reaches Laravel through index.php and comes
    // back as the home page, in UTF-8.
    //
    // Used by:
    //   - PHPUnit — the Server suite (runTests.sh)
    // -----------------------------------------------------------

    public function testHomePageIsServedByTheApp(): void
    {
        $this->world->seed();

        $response = $this->http->get('/');

        $this->assertSame(200, $response->status);
        $this->assertSame('text/html; charset=UTF-8', $response->header('Content-Type'));
        $this->assertStringContainsString('<h1 class="heading">BitLitas</h1>', $response->body);
        $this->assertStringContainsString('Iš 18.3 mln. jau iškasta 0</p>', $response->body);
    }






    // -----------------------------------------------------------
    // testIndexPhpIsTheHomePageToo
    // -----------------------------------------------------------
    //
    // The front controller asked for by name answers with the
    // home page.
    //
    // Used by:
    //   - PHPUnit — the Server suite (runTests.sh)
    // -----------------------------------------------------------

    public function testIndexPhpIsTheHomePageToo(): void
    {
        $this->world->seed();

        $response = $this->http->get('/index.php');

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('<h1 class="heading">BitLitas</h1>', $response->body);
    }






    // -----------------------------------------------------------
    // testUnknownPathGetsTheAppsNotFoundPage
    // -----------------------------------------------------------
    //
    // A path no file and no route answers reaches Laravel,
    // which renders the framework's 404 page.
    //
    // Used by:
    //   - PHPUnit — the Server suite (runTests.sh)
    // -----------------------------------------------------------

    public function testUnknownPathGetsTheAppsNotFoundPage(): void
    {
        $response = $this->http->get('/no/such/page');

        $this->assertSame(404, $response->status);
        $this->assertStringContainsString('Sorry, the page you are looking for could not be found.', $response->body);
    }






    // -----------------------------------------------------------
    // testFilesOfPublicComeFromNginx
    // -----------------------------------------------------------
    //
    // Each file of public/ is answered by nginx with its type
    // and its whole size, and sets no cookie — Laravel never
    // saw the request.
    //
    // Used by:
    //   - PHPUnit — the Server suite (runTests.sh)
    // -----------------------------------------------------------

    /** @dataProvider publicFiles */
    public function testFilesOfPublicComeFromNginx(string $path, string $type): void
    {
        $response = $this->http->head($path);

        $this->assertSame(200, $response->status);
        $this->assertSame($type, $response->header('Content-Type'));
        $this->assertSame((string) filesize(public_path(ltrim($path, '/'))), $response->header('Content-Length'));
        $this->assertSame([], $response->cookies());
    }






    // -----------------------------------------------------------
    // publicFiles
    // -----------------------------------------------------------
    //
    // Files of public/ the page links, with the type nginx
    // gives each.
    //
    // Used by:
    //   - testFilesOfPublicComeFromNginx (above)
    // -----------------------------------------------------------

    public function publicFiles(): array
    {
        return [
            'the stylesheet'     => ['/css/main.css', 'text/css'],
            'the script'         => ['/js/main.js', 'application/javascript'],
            'the logo'           => ['/img/logo.png', 'image/png'],
            'the background'     => ['/img/bg-default.jpg', 'image/jpeg'],
            'the tab icon'       => ['/img/favicon/icon.ico', 'image/x-icon'],
            'the root icon'      => ['/favicon.ico', 'image/x-icon'],
            'the film, webm'     => ['/video/bitlitas.webm', 'video/webm'],
            'the film, mp4'      => ['/video/bitlitas.mp4', 'video/mp4'],
            'the Linux wallet'   => ['/downloads/v1/bitlitas.tar.gz', 'application/octet-stream'],
            'the Windows wallet' => ['/downloads/v1/bitlitas.exe', 'application/octet-stream'],
            'robots.txt'         => ['/robots.txt', 'text/plain'],
        ];
    }






    // -----------------------------------------------------------
    // testFolderIsNeverListed
    // -----------------------------------------------------------
    //
    // A folder of public/ asked for with its slash is refused,
    // not listed.
    //
    // Used by:
    //   - PHPUnit — the Server suite (runTests.sh)
    // -----------------------------------------------------------

    public function testFolderIsNeverListed(): void
    {
        $response = $this->http->get('/css/');

        $this->assertSame(403, $response->status);
        $this->assertStringNotContainsString('main.css', $response->body);
    }
}
