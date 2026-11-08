<?php

// -----------------------------------------------------------
//  [*] PhpRuntimeTest — the PHP the image ships
//
//  The Dockerfile builds on php:8.4.27-fpm-alpine — the
//  release that fixes PHP2Shell (CVE-2026-109618 and
//  CVE-2026-109619) — and adds pdo_mysql, mbstring and gd;
//  curl, OpenSSL and the rest come with the official image.
//  Session upload progress is off, PHP's own mitigation of
//  CVE-2026-115560, which no release fixes yet. php-fpm serves
//  the app as the container's user, 1000, runs only files
//  ending in .php, and hands every worker the container's
//  environment — clear_env = no, from the official image's
//  docker.conf —
//  which is the only way the variables docker-compose.yml
//  sets (APP_DEBUG, DB_*, BITLITAS_DAEMON_URL) reach Laravel.
//  The settings are read from php-fpm -tt, which prints the
//  configuration php-fpm would run with.
//
//  Used by:
//    - PHPUnit — the Platform suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Platform;

use Tests\TestCase;








// -----------------------------------------------------------
// PhpRuntimeTest
// -----------------------------------------------------------
//
// Reads the running PHP and php-fpm's effective settings.
//
//   fpmSettings — php-fpm's settings, by name
//
// Used by:
//   - PHPUnit — the Platform suite (runTests.sh)
// -----------------------------------------------------------

class PhpRuntimeTest extends TestCase
{

    // What the app and Laravel 13 load: PDO's MySQL driver,
    // mbstring, tokenizer, ctype, dom and json for the
    // framework, OpenSSL for its encrypter, curl for the node,
    // and gd, which the Dockerfile installs
    private const EXTENSIONS = ['ctype', 'curl', 'dom', 'gd', 'json', 'mbstring', 'openssl', 'pdo_mysql', 'tokenizer'];






    // -----------------------------------------------------------
    // testPhpIsTheImagesVersion
    // -----------------------------------------------------------
    //
    // PHP 8.4.27, the image's pinned tag: a version change
    // must be a decision, not an accident of a base image.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testPhpIsTheImagesVersion(): void
    {
        $this->assertSame('8.4.27', PHP_VERSION);
    }






    // -----------------------------------------------------------
    // testSessionUploadProgressIsOff
    // -----------------------------------------------------------
    //
    // session.upload_progress.enabled is off for the command
    // line and for php-fpm alike: the Dockerfile's ini file
    // sits in the conf.d folder both read, and CVE-2026-115560
    // needs the feature on.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testSessionUploadProgressIsOff(): void
    {
        $this->assertSame('0', ini_get('session.upload_progress.enabled'));

        preg_match('/^session\.upload_progress\.enabled => (\S+)/m', (string) shell_exec('php-fpm -i 2>/dev/null'), $match);
        $this->assertSame('Off', $match[1] ?? null);
    }






    // -----------------------------------------------------------
    // testExtensionsTheAppNeedsAreLoaded
    // -----------------------------------------------------------
    //
    // Every extension the app and the framework call into is
    // there.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testExtensionsTheAppNeedsAreLoaded(): void
    {
        $missing = array_values(array_filter(self::EXTENSIONS, function (string $extension) {
            return !extension_loaded($extension);
        }));

        $this->assertSame([], $missing);
    }






    // -----------------------------------------------------------
    // testPhpFpmHandsWorkersTheEnvironment
    // -----------------------------------------------------------
    //
    // clear_env is off, so a worker sees the variables the
    // container was started with; with it on, Laravel would
    // fall back on its defaults for the database and the node.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testPhpFpmHandsWorkersTheEnvironment(): void
    {
        $this->assertSame('no', $this->fpmSettings()['clear_env'] ?? null);
    }






    // -----------------------------------------------------------
    // testPhpFpmServesAsTheContainersUser
    // -----------------------------------------------------------
    //
    // The pool names no user or group — php-fpm prints both as
    // undefined — so its workers run as whoever starts
    // php-fpm: user 1000, the container's and the owner of
    // storage/logs (runUpdateThisStack.sh chowns the bind
    // mount to 1000:1000). They run only PHP's default .php
    // and .phar files — nginx hands them nothing but paths
    // ending in .php.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testPhpFpmServesAsTheContainersUser(): void
    {
        $settings = $this->fpmSettings();

        $this->assertSame('undefined', $settings['user'] ?? null);
        $this->assertSame('undefined', $settings['group'] ?? null);
        $this->assertSame('.php .phar', $settings['security.limit_extensions'] ?? null);
    }






    // -----------------------------------------------------------
    // fpmSettings
    // -----------------------------------------------------------
    //
    // php-fpm -tt prints every setting of the www pool as a
    // NOTICE line, "name = value"; the last line of a name
    // wins, as in php-fpm itself. A value php-fpm prints in
    // quotes or angle brackets is unwrapped.
    //
    // Used by:
    //   - the tests above
    //   - PlatformDefectsTest (this folder)
    // -----------------------------------------------------------

    public static function fpmSettings(): array
    {
        $settings = [];
        foreach (explode("\n", (string) shell_exec('php-fpm -tt 2>&1')) as $line) {
            if (preg_match('/NOTICE:\s+([a-z_.\[\]]+) = (.*)$/', $line, $match)) {
                $settings[$match[1]] = trim($match[2], " \t\"'<>");
            }
        }
        return $settings;
    }
}
