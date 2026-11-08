<?php

// -----------------------------------------------------------
//  [*] TemplatesTest — the pages and the files they link
//
//  The site is one page: welcome.blade.php inside
//  core/layout.blade.php, with core/footer.blade.php. Every
//  template compiles to valid PHP; every file of the site's
//  own that the page links — its stylesheets, its scripts
//  and the libraries under js/lib, its pictures, the
//  background film, the two wallet downloads — ships in
//  public/, and so does every file main.css imports or
//  points to, the Open Sans web font included. The page
//  loads nothing from another server but the one embed that
//  cannot be copied, Facebook's page widget. The downloads
//  are the 2018 v1.0.2 wallets: a tarball holding bitlitasd
//  and the GUI wallet, and a Windows executable. The
//  background film is muted, so a browser lets it start;
//  every picture has an alt; and the root favicon.ico is the
//  page's icon.
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
//   views          — every template, by path
//   stylesheetUrls — what a stylesheet loads
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
        'img/paypal.png',
        'img/phone.png',
        'img/programoje.png',
        'img/windows.png',
        'js/lib/jquery-3.7.1.min.js',
        'js/lib/modernizr-2.8.3.min.js',
        'js/lib/respond-1.4.2.min.js',
        'js/main.js',
        'video/bitlitas.mp4',
        'video/bitlitas.webm',
    ];

    // The servers the page loads from besides its own:
    // Facebook's, for its page widget, which cannot be copied
    // to the site
    private const OUTSIDE_HOSTS = [
        'www.facebook.com',
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
    // testPageLoadsNothingElseFromOutside
    // -----------------------------------------------------------
    //
    // Every script, picture, frame and stylesheet the page
    // loads from another server comes from one of
    // OUTSIDE_HOSTS; a library or a font fetched from a CDN
    // fails here. Links a visitor follows are no loads and
    // are left out.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testPageLoadsNothingElseFromOutside(): void
    {
        $hosts = [];
        foreach ($this->views() as $path) {
            $template = file_get_contents($path);
            preg_match_all('/\bsrc=["\']((?:https?:)?\/\/[^"\']+)["\']/', $template, $sources);
            preg_match_all('/<link\b[^>]*\bhref=["\']((?:https?:)?\/\/[^"\']+)["\']/', $template, $links);
            foreach (array_merge($sources[1], $links[1]) as $address) {
                $hosts[] = parse_url($address, PHP_URL_HOST);
            }
        }
        $hosts = array_values(array_unique($hosts));
        sort($hosts);

        $this->assertSame(self::OUTSIDE_HOSTS, $hosts);
    }






    // -----------------------------------------------------------
    // testStylesheetsReferencesShip
    // -----------------------------------------------------------
    //
    // Everything main.css imports or points to is in public/,
    // and so are the two Open Sans files fonts.css points to,
    // with the font's licence.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testStylesheetsReferencesShip(): void
    {
        $main = $this->stylesheetUrls('main.css');
        $this->assertSame(['fonts.css', 'normalize.css', 'grid.css', '../img/bg-default.jpg'], $main);

        $fonts = array_values(array_unique($this->stylesheetUrls('fonts.css')));
        $this->assertSame(['../fonts/open-sans/open-sans-latin-ext.woff2', '../fonts/open-sans/open-sans-latin.woff2'], $fonts);

        foreach (array_merge($main, $fonts, ['../fonts/open-sans/OFL.txt']) as $url) {
            $this->assertFileExists(public_path('css/' . $url));
        }
    }






    // -----------------------------------------------------------
    // testStylesheetsLoadNothingFromOutside
    // -----------------------------------------------------------
    //
    // No stylesheet in public/css imports or points to another
    // server: the fonts, pictures and stylesheets they name
    // are all the site's own.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testStylesheetsLoadNothingFromOutside(): void
    {
        $stylesheets = array_map('basename', glob(public_path('css/*.css')));
        $this->assertSame(['fonts.css', 'grid.css', 'ie.css', 'main.css', 'normalize.css'], $stylesheets);

        foreach ($stylesheets as $stylesheet) {
            foreach ($this->stylesheetUrls($stylesheet) as $url) {
                $this->assertDoesNotMatchRegularExpression('#^(https?:)?//#', $url, "$stylesheet loads $url");
            }
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
    // testBackgroundFilmIsMutedSoItPlays
    // -----------------------------------------------------------
    //
    // Both files of the background film carry a sound track,
    // and browsers refuse to start a film with sound on their
    // own: the video tag is muted, and plays inline on a
    // phone, so the film starts when main.js asks it to.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testBackgroundFilmIsMutedSoItPlays(): void
    {
        preg_match('/<video\b[^>]*>/', file_get_contents(resource_path('views/welcome.blade.php')), $tag);

        $this->assertSame('<video loop muted playsinline id=bg-video class=fullscreen-video>', $tag[0]);
    }






    // -----------------------------------------------------------
    // testEveryPictureHasAnAlt
    // -----------------------------------------------------------
    //
    // Every img of the page carries an alt: the words a screen
    // reader says for it, or an empty one for a picture beside
    // words that already say it.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testEveryPictureHasAnAlt(): void
    {
        foreach ($this->views() as $name => $path) {
            preg_match_all('/<img\b[^>]*>/', file_get_contents($path), $pictures);
            foreach ($pictures[0] as $picture) {
                $this->assertMatchesRegularExpression('/\salt(?:[\s>]|=)/', $picture, "$name: $picture has no alt");
            }
        }
    }






    // -----------------------------------------------------------
    // testRootIconIsTheSitesIcon
    // -----------------------------------------------------------
    //
    // favicon.ico at the root, which a browser asks for on any
    // answer that names no icon of its own — an error page,
    // the JSON of /blokai — is the icon the page names.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testRootIconIsTheSitesIcon(): void
    {
        $this->assertFileEquals(public_path('img/favicon/icon.ico'), public_path('favicon.ico'));
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






    // -----------------------------------------------------------
    // stylesheetUrls
    // -----------------------------------------------------------
    //
    // Every address a stylesheet of public/css imports or
    // points to, in the order it names them, leaving out the
    // pictures it carries inline as data: addresses.
    //
    // Used by:
    //   - the tests above
    // -----------------------------------------------------------

    private function stylesheetUrls(string $stylesheet): array
    {
        preg_match_all('/url\(\s*["\']?([^"\')]+)["\']?\s*\)/', file_get_contents(public_path('css/' . $stylesheet)), $matches);
        return array_values(array_filter($matches[1], function (string $url) {
            return strpos($url, 'data:') !== 0;
        }));
    }
}
