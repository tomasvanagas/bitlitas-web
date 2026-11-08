<?php

// -----------------------------------------------------------
//  [*] WebServerConfigTest — nginx and supervisord as built
//
//  The Dockerfile writes the site's nginx server block —
//  public/ as the root, every path that is no file handed
//  to index.php, PHP passed to php-fpm on port 9000, the
//  temporary files in /tmp — and a supervisord
//  configuration that keeps php-fpm and nginx running in
//  the foreground, restarts either one that dies and passes
//  their output on as the container's. Alpine's nginx.conf
//  keeps the server's version out of its answers, and the
//  Dockerfile takes its user switch and its log files out,
//  since the container runs as user 1000 on a read-only
//  root filesystem; every server process runs as 1000. The
//  Server suite checks the behaviour these files lead to;
//  this test pins the files and the processes.
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
// Reads the configuration files of the image and the
// processes the container runs.
//
//   processUsers — the running processes' users, by name
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
    // path; nginx keeps its temporary files in /tmp, a tmpfs,
    // since it can write nowhere else.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testNginxServesPublicThroughIndexPhp(): void
    {
        $site = preg_replace('/\s+/', ' ', file_get_contents(self::SITE));

        foreach (['client_body', 'fastcgi', 'proxy', 'scgi', 'uwsgi'] as $kind) {
            $this->assertStringContainsString("{$kind}_temp_path /tmp/nginx-" . str_replace('_', '-', $kind) . ';', $site);
        }
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
        $this->assertMatchesRegularExpression('/^\s*server_tokens off;/m', file_get_contents('/etc/nginx/nginx.conf'));
    }






    // -----------------------------------------------------------
    // testNginxRunsWithoutRoot
    // -----------------------------------------------------------
    //
    // nginx.conf names no user to switch to — only root can —
    // and logs to the container's output, not to
    // /var/log/nginx, which user 1000 cannot write.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testNginxRunsWithoutRoot(): void
    {
        $main = file_get_contents('/etc/nginx/nginx.conf');

        $this->assertDoesNotMatchRegularExpression('/^\s*user\s/m', $main);
        $this->assertMatchesRegularExpression('/^error_log \/dev\/stderr warn;$/m', $main);
        $this->assertMatchesRegularExpression('/^\s*access_log \/dev\/stdout main;$/m', $main);
        $this->assertStringNotContainsString('/var/log/nginx', $main);
    }






    // -----------------------------------------------------------
    // testSupervisorKeepsBothServersRunning
    // -----------------------------------------------------------
    //
    // supervisord stays in the foreground as the container's
    // process and runs php-fpm and nginx, each in the
    // foreground and restarted when it dies. Its own log goes
    // to the container's output alone, as does both servers'
    // output, and its pid file and nginx's go to /tmp. The
    // file is read as supervisord reads it: a ';' starts a
    // comment only after a space, so nginx's -g directives
    // stay whole.
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

        $output = [
            'stdout_logfile'          => '/dev/stdout',
            'stdout_logfile_maxbytes' => '0',
            'stderr_logfile'          => '/dev/stderr',
            'stderr_logfile_maxbytes' => '0',
        ];
        $this->assertSame([
            'supervisord'     => ['nodaemon' => 'true', 'logfile' => '/dev/null', 'logfile_maxbytes' => '0', 'pidfile' => '/tmp/supervisord.pid'],
            'program:php-fpm' => ['command' => 'php-fpm -F', 'autorestart' => 'true'] + $output,
            'program:nginx'   => ['command' => 'nginx -e /dev/stderr -g "daemon off; pid /tmp/nginx.pid;"', 'autorestart' => 'true'] + $output,
        ], $sections);
    }






    // -----------------------------------------------------------
    // testServersRunAsTheContainersUser
    // -----------------------------------------------------------
    //
    // supervisord, every nginx process and every php-fpm
    // process run as user 1000, the container's — the suite's
    // own user — with no root anywhere to drop from.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testServersRunAsTheContainersUser(): void
    {
        $this->assertSame(1000, posix_geteuid());

        $users = $this->processUsers();
        foreach (['supervisord', 'nginx', 'php-fpm'] as $server) {
            $this->assertNotEmpty($users[$server] ?? [], "no $server process runs");
            $this->assertSame([1000], array_values(array_unique($users[$server])), "a $server process runs as another user");
        }
    }






    // -----------------------------------------------------------
    // processUsers
    // -----------------------------------------------------------
    //
    // The effective user id of every process the container
    // runs, grouped by the process's name, as /proc/<pid>/status
    // gives them; a process that ends while it is read is left
    // out.
    //
    // Used by:
    //   - testServersRunAsTheContainersUser (above)
    // -----------------------------------------------------------

    private function processUsers(): array
    {
        $users = [];
        foreach (glob('/proc/[0-9]*/status') as $file) {
            $status = @file_get_contents($file);
            if ($status !== false && preg_match('/^Name:\s+(\S+)$/m', $status, $name) && preg_match('/^Uid:\s+\d+\s+(\d+)/m', $status, $uid)) {
                $users[$name[1]][] = (int) $uid[1];
            }
        }
        return $users;
    }
}
