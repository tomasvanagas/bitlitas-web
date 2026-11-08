<?php

// -----------------------------------------------------------
//  [*] WebServerConfigTest — nginx and supervisord as built
//
//  The Dockerfile writes the site's nginx server block —
//  public/ as the root, every path that is no file handed
//  to index.php, PHP passed to php-fpm on port 9000 — and a
//  supervisord configuration that keeps php-fpm and nginx
//  running in the foreground and restarts either one that
//  dies. Alpine's nginx.conf keeps the server's version out
//  of its answers. The Server suite checks the behaviour
//  these files lead to; this test pins the files.
//
//  Used by:
//    - PHPUnit — the Platform suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Platform;

use Tests\TestCase;








// -----------------------------------------------------------
// WebServerConfigTest
// -----------------------------------------------------------
//
// Reads the configuration files of the image.
//
// Used by:
//   - PHPUnit — the Platform suite (runTests.sh)
// -----------------------------------------------------------

class WebServerConfigTest extends TestCase
{

    // Where the Dockerfile writes the two files
    private const SITE = '/etc/nginx/http.d/default.conf';
    private const SUPERVISOR = '/etc/supervisor/conf.d/supervisord.conf';






    // -----------------------------------------------------------
    // testNginxServesPublicThroughIndexPhp
    // -----------------------------------------------------------
    //
    // One server on port 80 with public/ as its root, the
    // front controller for every path that is no file or
    // folder, and PHP sent to php-fpm with the script's full
    // path.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testNginxServesPublicThroughIndexPhp(): void
    {
        $site = preg_replace('/\s+/', ' ', file_get_contents(self::SITE));

        $this->assertStringContainsString('listen 80;', $site);
        $this->assertStringContainsString('root /var/www/html/public;', $site);
        $this->assertStringContainsString('index index.php;', $site);
        $this->assertStringContainsString('location / { try_files $uri $uri/ /index.php?$query_string; }', $site);
        $this->assertStringContainsString('location ~ \.php$ { fastcgi_pass 127.0.0.1:9000;', $site);
        $this->assertStringContainsString('fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;', $site);
    }






    // -----------------------------------------------------------
    // testNginxNamesNoVersion
    // -----------------------------------------------------------
    //
    // server_tokens off in the main configuration.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testNginxNamesNoVersion(): void
    {
        $this->assertRegExp('/^\s*server_tokens off;/m', file_get_contents('/etc/nginx/nginx.conf'));
    }






    // -----------------------------------------------------------
    // testSupervisorKeepsBothServersRunning
    // -----------------------------------------------------------
    //
    // supervisord stays in the foreground as the container's
    // process and runs php-fpm and nginx, each in the
    // foreground and restarted when it dies. The file is read
    // as supervisord reads it: a ';' starts a comment only
    // after a space, so nginx's "daemon off;" stays whole.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testSupervisorKeepsBothServersRunning(): void
    {
        $sections = [];
        foreach (file(self::SUPERVISOR, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (preg_match('/^\[(.+)\]$/', $line, $section)) {
                $name = $section[1];
                $sections[$name] = [];
            } elseif (isset($name) && strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $sections[$name][trim($key)] = trim($value);
            }
        }

        $this->assertSame([
            'supervisord'     => ['nodaemon' => 'true'],
            'program:php-fpm' => ['command' => 'php-fpm -F', 'autorestart' => 'true'],
            'program:nginx'   => ['command' => 'nginx -g "daemon off;"', 'autorestart' => 'true'],
        ], $sections);
    }
}
