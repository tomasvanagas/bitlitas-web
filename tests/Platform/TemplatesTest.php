<?php

// -----------------------------------------------------------
//  [*] TemplatesTest — the pages and the files they link
//
//  The site is one page: welcome.blade.php inside
//  core/layout.blade.php, with core/footer.blade.php. Every
//  template compiles to valid PHP; every file of the site's
//  own that the page links — its stylesheets and script, its
//  pictures, the background film, the two wallet downloads
//  — ships in public/, and so does every file main.css
//  imports or points to. The downloads are the 2018 v1.0.2
//  wallets: a tarball holding bitlitasd and the GUI wallet,
//  and a Windows executable.
//
//  Used by:
//    - PHPUnit — the Platform suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Platform;

use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;








// -----------------------------------------------------------
// TemplatesTest
// -----------------------------------------------------------
//
// Reads resources/views and public/.
//
//   views — every template, by path
//
// Used by:
//   - PHPUnit — the Platform suite (runTests.sh)
// -----------------------------------------------------------

class TemplatesTest extends TestCase
{

    // Every file of the site's own the page links, as the
    // templates write it; a new link must be added here
    private const LINKED = [
        '/img/google-play-badge.png',
        'css/ie.css',
        'css/main.css?v=1.1',
        'downloads/v1/bitlitas.exe',
        'downloads/v1/bitlitas.tar.gz',
        'img/favicon/icon.ico',
        'img/linux.png',
        'img/logo.png',
        'img/phone.png',
        'img/programoje.png',
        'img/windows.png',
        'js/main.js',
        'video/bitlitas.mp4',
        'video/bitlitas.webm',
    ];






    // -----------------------------------------------------------
    // testEveryTemplateCompilesToValidPhp
    // -----------------------------------------------------------
    //
    // Blade's output for every template parses as PHP.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testEveryTemplateCompilesToValidPhp(): void
    {
        $views = $this->views();
        $this->assertSame(['core/footer.blade.php', 'core/layout.blade.php', 'welcome.blade.php'], array_keys($views));

        foreach ($views as $name => $path) {
            $compiled = app('blade.compiler')->compileString(file_get_contents($path));
            try {
                token_get_all($compiled, TOKEN_PARSE);
            } catch (\ParseError $error) {
                $this->fail("$name compiles to invalid PHP: " . $error->getMessage());
            }
        }
    }






    // -----------------------------------------------------------
    // testFilesThePageLinksShip
    // -----------------------------------------------------------
    //
    // The page links exactly the files of LINKED — every
    // src and href that is no outside address and no page —
    // and each of them is in public/.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testFilesThePageLinksShip(): void
    {
        $linked = [];
        foreach ($this->views() as $path) {
            preg_match_all('/\b(?:src|href)=["\']([^"\']+)["\']/', file_get_contents($path), $matches);
            foreach ($matches[1] as $link) {
                if (!preg_match('#^(https?:)?//|^/$|^\##', $link)) {
                    $linked[] = $link;
                }
            }
        }
        sort($linked);

        $this->assertSame(self::LINKED, array_values(array_unique($linked)));
        foreach (self::LINKED as $link) {
            $this->assertFileExists(public_path(ltrim(strtok($link, '?'), '/')));
        }
    }






    // -----------------------------------------------------------
    // testStylesheetsReferencesShip
    // -----------------------------------------------------------
    //
    // Everything main.css imports or points to, outside the
    // web fonts it imports from Google, is in public/.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testStylesheetsReferencesShip(): void
    {
        preg_match_all('/url\(\s*["\']?([^"\')]+)["\']?\s*\)/', file_get_contents(public_path('css/main.css')), $matches);
        $local = array_values(array_filter($matches[1], function (string $url) {
            return !preg_match('#^(https?:|data:)#', $url);
        }));

        $this->assertSame(['normalize.css', 'grid.css', '../img/bg-default.jpg'], $local);
        foreach ($local as $url) {
            $this->assertFileExists(public_path('css/' . $url));
        }
    }






    // -----------------------------------------------------------
    // testDownloadsAreTheWallets
    // -----------------------------------------------------------
    //
    // The Linux download is a gzipped tarball holding the
    // daemon and the GUI wallet; the Windows one is a PE
    // executable.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testDownloadsAreTheWallets(): void
    {
        $names = [];
        foreach (new RecursiveIteratorIterator(new PharData(public_path('downloads/v1/bitlitas.tar.gz'))) as $entry) {
            $names[] = $entry->getFilename();
        }

        $this->assertContains('bitlitasd', $names);
        $this->assertContains('bitlitas-wallet-gui', $names);
        $this->assertSame('MZ', file_get_contents(public_path('downloads/v1/bitlitas.exe'), false, null, 0, 2));
    }






    // -----------------------------------------------------------
    // views
    // -----------------------------------------------------------
    //
    // Every .blade.php under resources/views, keyed by its
    // path inside that folder, in name order.
    //
    // Used by:
    //   - the tests above
    // -----------------------------------------------------------

    private function views(): array
    {
        $root = resource_path('views') . '/';
        $views = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if (substr($file->getFilename(), -10) === '.blade.php') {
                $views[substr($file->getPathname(), strlen($root))] = $file->getPathname();
            }
        }
        ksort($views);
        return $views;
    }
}
