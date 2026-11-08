<?php

// -----------------------------------------------------------
//  [*] PhpRuntimeTest — the PHP the image ships
//
//  The Dockerfile builds on php:7.4-fpm-alpine — PHP 7.4.33
//  — and adds pdo_mysql, mbstring and gd; curl, OpenSSL and
//  the rest come with the official image. php-fpm serves
//  the app as www-data, runs only files ending in .php, and
//  hands every worker the container's environment —
//  clear_env = no, from the official image's docker.conf —
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

    // What the app and Laravel 5.5 load: PDO's MySQL driver,
    // mbstring, tokenizer, ctype, dom and json for the
    // framework, OpenSSL for its encrypter, curl for the node,
    // and gd, which the Dockerfile installs
    private const EXTENSIONS = ['ctype', 'curl', 'dom', 'gd', 'json', 'mbstring', 'openssl', 'pdo_mysql', 'tokenizer'];






    // -----------------------------------------------------------
    // testPhpIsTheImagesVersion
    // -----------------------------------------------------------
    //
    // PHP 7.4.33, the last 7.4 release, which the image's tag
    // resolves to.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testPhpIsTheImagesVersion(): void
    {
        $this->assertSame('7.4.33', PHP_VERSION);
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
    // testPhpFpmServesAsWwwData
    // -----------------------------------------------------------
    //
    // The workers run as www-data, the owner of storage/logs
    // (runUpdateThisStack.sh chowns the bind mount to 82:82),
    // and run only PHP 7.4's default .php and .phar files —
    // nginx hands them nothing but paths ending in .php.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testPhpFpmServesAsWwwData(): void
    {
        $settings = $this->fpmSettings();

        $this->assertSame('www-data', $settings['user'] ?? null);
        $this->assertSame('www-data', $settings['group'] ?? null);
        $this->assertSame(82, posix_getpwnam('www-data')['uid']);
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
