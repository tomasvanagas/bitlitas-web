<?php

// -----------------------------------------------------------
//  [*] ApacheConfigTest — how the image configures Apache
//
//  The Apache half of the image, read from its files: the
//  virtual host serves public/ on port 8080, sends every
//  path that is no file or folder to index.php, lists no
//  folder and reads no .htaccess; mod_rewrite, mod_remoteip
//  and mod_php are on, mod_remoteip trusting every proxy
//  to name the visitor; Apache names no version
//  and logs to /var/www/logs, a bind mount in production.
//  Every Apache process runs as user 1000, the container's.
//  The Server suite proves the same rules over HTTP.
//
//  Used by:
//    - PHPUnit — the Platform suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Platform;

use Tests\TestCase;








// -----------------------------------------------------------
// ApacheConfigTest
// -----------------------------------------------------------
//
// Reads the configuration files the Dockerfile puts in
// place, and the processes the container runs.
//
//   vhost        — the virtual host's text
//   processUsers — the running processes' users, by name
//
// Used by:
//   - PHPUnit — the Platform suite (runTests.sh)
// -----------------------------------------------------------

class ApacheConfigTest extends TestCase
{

    // The virtual host the Dockerfile installs
    private const VHOST = '/etc/apache2/sites-available/000-default.conf';






    // -----------------------------------------------------------
    // testVirtualHostServesThePublicFolderOnPort8080
    // -----------------------------------------------------------
    //
    // Apache listens on 8080 — the port the Caddy endpoint
    // proxies to — and the enabled virtual host's document
    // root is public/, so nothing above it is ever a URL.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testVirtualHostServesThePublicFolderOnPort8080(): void
    {
        $this->assertSame("Listen 8080\n", file_get_contents('/etc/apache2/ports.conf'));
        $this->assertFileExists('/etc/apache2/sites-enabled/000-default.conf');
        $this->assertMatchesRegularExpression('#<VirtualHost \*:8080>#', $this->vhost());
        $this->assertMatchesRegularExpression('#^\s*DocumentRoot /var/www/html/public/?\s*$#m', $this->vhost());
    }






    // -----------------------------------------------------------
    // testPathThatIsNoFileGoesToIndexPhp
    // -----------------------------------------------------------
    //
    // public/'s block rewrites a path that names no file and
    // no folder to index.php, the front controller.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testPathThatIsNoFileGoesToIndexPhp(): void
    {
        $this->assertTrue((bool) preg_match('#<Directory /var/www/html/public\s*>(.*?)</Directory>#s', $this->vhost(), $block));

        $this->assertMatchesRegularExpression('#RewriteEngine On\s+RewriteCond %\{REQUEST_FILENAME\} !-f\s+RewriteCond %\{REQUEST_FILENAME\} !-d\s+RewriteRule \^ index\.php \[L\]#', $block[1]);
    }






    // -----------------------------------------------------------
    // testAppFolderListsNothingAndReadsNoHtaccess
    // -----------------------------------------------------------
    //
    // The app folder's block turns Indexes off and lets no
    // .htaccess change the configuration — it overrides the
    // AllowOverride All the official image's docker-php.conf
    // gives /var/www/.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testAppFolderListsNothingAndReadsNoHtaccess(): void
    {
        $this->assertTrue((bool) preg_match('#<Directory /var/www/html\s*>(.*?)</Directory>#s', $this->vhost(), $block));

        $this->assertMatchesRegularExpression('#Options -Indexes#', $block[1]);
        $this->assertMatchesRegularExpression('#AllowOverride None#', $block[1]);
    }






    // -----------------------------------------------------------
    // testRewriteRemoteIpAndPhpAreEnabled
    // -----------------------------------------------------------
    //
    // The front controller needs mod_rewrite, the visitor's
    // address behind the proxies needs mod_remoteip reading
    // X-Forwarded-For, and the app needs mod_php.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testRewriteRemoteIpAndPhpAreEnabled(): void
    {
        $this->assertFileExists('/etc/apache2/mods-enabled/rewrite.load');
        $this->assertFileExists('/etc/apache2/mods-enabled/remoteip.load');
        $this->assertFileExists('/etc/apache2/mods-enabled/php.load');
        $this->assertMatchesRegularExpression('#^RemoteIPHeader X-Forwarded-For$#m', $this->vhost());
    }






    // -----------------------------------------------------------
    // testEveryProxyIsTrusted
    // -----------------------------------------------------------
    //
    // RemoteIPInternalProxy lets the addresses it names put
    // the visitor's address in X-Forwarded-For — the one the
    // access log records and Laravel reads. It names every
    // IPv4 and IPv6 address, private and public alike, as the
    // two halves of each family — mod_remoteip takes no mask
    // of zero bits — since Apache sits behind proxies of every
    // kind; the Server suite's ClientAddressTest proves it
    // over HTTP.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testEveryProxyIsTrusted(): void
    {
        preg_match_all('#^RemoteIPInternalProxy\s+(.+)$#m', $this->vhost(), $lines);

        $this->assertSame(['0.0.0.0/1 128.0.0.0/1 ::/1 8000::/1'], $lines[1]);
    }






    // -----------------------------------------------------------
    // testApacheNamesNoVersion
    // -----------------------------------------------------------
    //
    // ServerTokens Prod and ServerSignature Off, read after
    // Debian's security.conf, so neither the Server header nor
    // Apache's own error pages name a version.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testApacheNamesNoVersion(): void
    {
        $this->assertMatchesRegularExpression('#^ServerTokens Prod$#m', $this->vhost());
        $this->assertMatchesRegularExpression('#^ServerSignature Off$#m', $this->vhost());
    }






    // -----------------------------------------------------------
    // testLogsGoToTheLogFolder
    // -----------------------------------------------------------
    //
    // Both logs go to /var/www/logs, the access log with the
    // visitor's address first — %a, the one mod_remoteip
    // resolved.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testLogsGoToTheLogFolder(): void
    {
        $this->assertMatchesRegularExpression('#^\s*ErrorLog /var/www/logs/error\.log$#m', $this->vhost());
        $this->assertMatchesRegularExpression('#^\s*LogFormat "%a .*" proxy$#m', $this->vhost());
        $this->assertMatchesRegularExpression('#^\s*CustomLog /var/www/logs/access\.log proxy$#m', $this->vhost());
    }






    // -----------------------------------------------------------
    // testApacheRunsAsTheContainersUser
    // -----------------------------------------------------------
    //
    // Every Apache process runs as user 1000 — the
    // container's, the suite's own — with no root anywhere to
    // drop from, so the User of Apache's envvars is never
    // used.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testApacheRunsAsTheContainersUser(): void
    {
        $this->assertSame(1000, posix_geteuid());

        $users = $this->processUsers();
        $this->assertNotEmpty($users['apache2'] ?? [], 'no Apache process runs');
        $this->assertSame([1000], array_values(array_unique($users['apache2'])), 'an Apache process runs as another user');
    }






    // -----------------------------------------------------------
    // vhost
    // -----------------------------------------------------------
    //
    // The virtual host's configuration text.
    //
    // Used by:
    //   - the tests above
    // -----------------------------------------------------------

    private function vhost(): string
    {
        return file_get_contents(self::VHOST);
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
    //   - testApacheRunsAsTheContainersUser (above)
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
