<?php

// -----------------------------------------------------------
//  [*] PhpRuntimeTest — the PHP the image ships
//
//  The Dockerfile builds on php:8.4.27-apache — the release
//  that fixes PHP2Shell (CVE-2026-109618 and CVE-2026-109619)
//  — and adds pdo_mysql and gd; mbstring, curl, OpenSSL and
//  the rest come with the official image. php.ini is PHP's
//  production file, so PHP's own errors go to the log and
//  never into a page, and expose_php is off. Session upload
//  progress is off, PHP's own mitigation of CVE-2026-115560,
//  which no release fixes yet. Apache runs PHP as mod_php,
//  built with the command line this suite runs on and
//  reading the same ini files, so the command line's
//  settings are the site's. Apache's processes inherit the
//  container's environment, the only way the variables
//  docker-compose.yml sets (APP_DEBUG, DB_*,
//  BITLITAS_DAEMON_URL) reach Laravel; the Server suite
//  reaches the database and the stand-in node through them.
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
// Reads the running PHP: its version, settings and
// extensions.
//
// Used by:
//   - PHPUnit — the Platform suite (runTests.sh)
// -----------------------------------------------------------

class PhpRuntimeTest extends TestCase
{

    // What the app and Laravel 13 load: PDO's MySQL driver,
    // mbstring, tokenizer, ctype, dom and json for the
    // framework, OpenSSL for its encrypter, curl for the node,
    // and gd; the Dockerfile installs pdo_mysql and gd
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
    // testPhpShowsNoErrorsToVisitors
    // -----------------------------------------------------------
    //
    // PHP keeps its own errors out of the pages Apache serves:
    // php.ini is the production one, with display_errors off,
    // so an error PHP raises before Laravel's handler is in
    // place never prints the server's paths. A fresh command
    // line PHP is asked: it reads the ini files mod_php
    // reads, untouched by whatever the suite's own process
    // sets.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testPhpShowsNoErrorsToVisitors(): void
    {
        $this->assertSame(PHP_CONFIG_FILE_PATH . '/php.ini', php_ini_loaded_file());
        $value = trim((string) shell_exec('php -r "echo ini_get(\'display_errors\');"'));
        $this->assertContains(strtolower($value), ['', '0', 'off'], "PHP runs with display_errors $value");
    }






    // -----------------------------------------------------------
    // testPhpNamesNoVersion
    // -----------------------------------------------------------
    //
    // expose_php is off, so PHP adds no X-Powered-By naming
    // its version to an answer; the Server suite checks the
    // answers themselves.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testPhpNamesNoVersion(): void
    {
        $this->assertSame('', ini_get('expose_php'));
    }






    // -----------------------------------------------------------
    // testSessionUploadProgressIsOff
    // -----------------------------------------------------------
    //
    // session.upload_progress.enabled is off: the Dockerfile's
    // ini file sits in the conf.d folder that the command line
    // and mod_php both read, and CVE-2026-115560 needs the
    // feature on.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testSessionUploadProgressIsOff(): void
    {
        $this->assertSame('/usr/local/etc/php/conf.d', PHP_CONFIG_FILE_SCAN_DIR);
        $this->assertFileExists(PHP_CONFIG_FILE_SCAN_DIR . '/zz-session-upload-progress.ini');
        $this->assertSame('0', ini_get('session.upload_progress.enabled'));
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
}
