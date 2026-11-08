<?php

// -----------------------------------------------------------
//  [*] FrontControllerTest — how Apache hands requests over
//
//  The routing half of the web server: a path that is a file
//  of public/ is served by Apache itself, with its type and
//  without passing through Laravel; any other path goes to
//  index.php, and Laravel answers one it has no route for
//  with its 404 page. A folder is never listed, and one
//  asked for without its slash is sent to the slashed
//  address on https.
//
//  Used by:
//    - PHPUnit — the Server suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Server;

use PHPUnit\Framework\Attributes\DataProvider;
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
        $this->assertSame('text/html; charset=utf-8', $response->header('Content-Type'));
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
        $this->assertStringContainsString('Not Found', $response->body);
    }






    // -----------------------------------------------------------
    // testFilesOfPublicComeFromApache
    // -----------------------------------------------------------
    //
    // Each file of public/ is answered by Apache with its type
    // and its whole size, and sets no cookie — Laravel never
    // saw the request. A HEAD of the scaffold's empty
    // favicon.ico gets no Content-Length at all, which reads
    // as the size it is, 0.
    //
    // Used by:
    //   - PHPUnit — the Server suite (runTests.sh)
    // -----------------------------------------------------------

    #[DataProvider('publicFiles')]
    public function testFilesOfPublicComeFromApache(string $path, string $type): void
    {
        $response = $this->http->head($path);

        $this->assertSame(200, $response->status);
        $this->assertSame($type, $response->header('Content-Type'));
        $this->assertSame(filesize(public_path(ltrim($path, '/'))), (int) $response->header('Content-Length'));
        $this->assertSame([], $response->cookies());
    }






    // -----------------------------------------------------------
    // publicFiles
    // -----------------------------------------------------------
    //
    // Files of public/ the page links, with the type Apache
    // gives each.
    //
    // Used by:
    //   - testFilesOfPublicComeFromApache (above)
    // -----------------------------------------------------------

    public static function publicFiles(): array
    {
        return [
            'the stylesheet'     => ['/css/main.css', 'text/css'],
            'the script'         => ['/js/main.js', 'text/javascript'],
            'the logo'           => ['/img/logo.png', 'image/png'],
            'the background'     => ['/img/bg-default.jpg', 'image/jpeg'],
            'the tab icon'       => ['/img/favicon/icon.ico', 'image/vnd.microsoft.icon'],
            'the root icon'      => ['/favicon.ico', 'image/vnd.microsoft.icon'],
            'the film, webm'     => ['/video/bitlitas.webm', 'video/webm'],
            'the film, mp4'      => ['/video/bitlitas.mp4', 'video/mp4'],
            'the Linux wallet'   => ['/downloads/v1/bitlitas.tar.gz', 'application/x-gzip'],
            'the Windows wallet' => ['/downloads/v1/bitlitas.exe', 'application/x-msdos-program'],
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






    // -----------------------------------------------------------
    // testFolderWithoutItsSlashIsSentToItOverHttps
    // -----------------------------------------------------------
    //
    // A folder asked for without its slash is redirected to
    // the slashed address on https://bitlitas.lt: mod_dir
    // builds it from the virtual host's ServerName, since TLS
    // ends at the endpoint.
    //
    // Used by:
    //   - PHPUnit — the Server suite (runTests.sh)
    // -----------------------------------------------------------

    public function testFolderWithoutItsSlashIsSentToItOverHttps(): void
    {
        $response = $this->http->get('/css');

        $this->assertSame(301, $response->status);
        $this->assertSame('https://bitlitas.lt/css/', $response->header('Location'));
    }
}
