<?php

// -----------------------------------------------------------
//  [*] ConfigTest — the app's configuration at run time
//
//  The image carries the .env file its Dockerfile writes: the
//  app's name, production, a key generated at build time,
//  the public URL, and the database settings as references
//  — ${DB_HOST} and the like — which the environment
//  docker-compose.yml gives the container fills in. Debug
//  mode follows APP_DEBUG, the full node's address comes from
//  BITLITAS_DAEMON_URL (config/services.php), and php-fpm,
//  serving as www-data, can write every folder Laravel
//  writes to.
//
//  Used by:
//    - PHPUnit — the Platform suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Platform;

use Tests\TestCase;








// -----------------------------------------------------------
// ConfigTest
// -----------------------------------------------------------
//
// Reads the booted app's configuration, the files around it
// and the environment run.sh exports — the one
// docker-compose.yml gives production, with test values.
//
//   withoutEnv — config files evaluated with variables unset
//
// Used by:
//   - PHPUnit — the Platform suite (runTests.sh)
// -----------------------------------------------------------

class ConfigTest extends TestCase
{

    // The folders Laravel writes to: sessions, compiled views,
    // the file cache (which also keeps the sync route's
    // throttle), the sync lock, and the log
    private const WRITABLE = [
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/framework/cache',
        'storage/framework',
        'storage/logs',
    ];






    // -----------------------------------------------------------
    // testEnvironmentFileHoldsReferencesNotSecrets
    // -----------------------------------------------------------
    //
    // .env names exactly what the Dockerfile writes; the
    // database password appears only as a reference, and the
    // key is a base64 AES-256 key.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testEnvironmentFileHoldsReferencesNotSecrets(): void
    {
        $lines = file(base_path('.env'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $values = [];
        foreach ($lines as $line) {
            list($name, $value) = explode('=', $line, 2);
            $values[$name] = $value;
        }
        $key = $values['APP_KEY'];
        unset($values['APP_KEY']);

        $this->assertSame([
            'APP_NAME'      => 'Bitlitas',
            'APP_ENV'       => 'production',
            'APP_URL'       => 'https://bitlitas.lt',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST'       => '${DB_HOST}',
            'DB_DATABASE'   => '${DB_DATABASE}',
            'DB_USERNAME'   => '${DB_USERNAME}',
            'DB_PASSWORD'   => '${DB_PASSWORD}',
        ], $values);
        $this->assertStringStartsWith('base64:', $key);
        $this->assertSame(32, strlen(base64_decode(substr($key, 7), true)));
    }






    // -----------------------------------------------------------
    // testDebugModeFollowsAppDebug
    // -----------------------------------------------------------
    //
    // APP_DEBUG=false, as docker-compose.yml sets it, switches
    // debug mode off.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testDebugModeFollowsAppDebug(): void
    {
        $this->assertSame('false', getenv('APP_DEBUG'));
        $this->assertFalse(config('app.debug'));
    }






    // -----------------------------------------------------------
    // testDatabaseSettingsComeFromTheEnvironment
    // -----------------------------------------------------------
    //
    // The references in .env are filled in from the
    // environment: the app reaches the database
    // docker-compose.yml names, with its user and password.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testDatabaseSettingsComeFromTheEnvironment(): void
    {
        $this->assertSame('mysql', config('database.default'));
        $mysql = config('database.connections.mysql');

        $this->assertSame(getenv('DB_HOST'), $mysql['host']);
        $this->assertSame(getenv('DB_DATABASE'), $mysql['database']);
        $this->assertSame(getenv('DB_USERNAME'), $mysql['username']);
        $this->assertSame(getenv('DB_PASSWORD'), $mysql['password']);
        $this->assertTrue($mysql['strict']);
    }






    // -----------------------------------------------------------
    // testNodeSettingsComeFromTheEnvironment
    // -----------------------------------------------------------
    //
    // BITLITAS_DAEMON_URL names the node; no login is set.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testNodeSettingsComeFromTheEnvironment(): void
    {
        $this->assertSame(getenv('BITLITAS_DAEMON_URL'), config('services.bitlitas.daemon_url'));
        $this->assertNull(config('services.bitlitas.daemon_login'));
    }






    // -----------------------------------------------------------
    // testNodeSettingsDefaultToALocalNode
    // -----------------------------------------------------------
    //
    // Without the variables, the address is bitlitasd's own
    // default RPC address, and there is no login.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testNodeSettingsDefaultToALocalNode(): void
    {
        $services = $this->withoutEnv(['BITLITAS_DAEMON_URL', 'BITLITAS_DAEMON_LOGIN'], 'services.php');

        $this->assertSame('http://127.0.0.1:18081', $services['bitlitas']['daemon_url']);
        $this->assertNull($services['bitlitas']['daemon_login']);
    }






    // -----------------------------------------------------------
    // testAppIsLithuanianAndRunsInUtc
    // -----------------------------------------------------------
    //
    // The locale is lt, the clock UTC, and the URL the public
    // one.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testAppIsLithuanianAndRunsInUtc(): void
    {
        $this->assertSame('lt', config('app.locale'));
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('https://bitlitas.lt', config('app.url'));
    }






    // -----------------------------------------------------------
    // testStorageIsWritableForPhpFpm
    // -----------------------------------------------------------
    //
    // The suite runs as www-data, php-fpm's user, and every
    // folder Laravel writes to takes its writes.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testStorageIsWritableForPhpFpm(): void
    {
        $this->assertSame('www-data', posix_getpwuid(posix_geteuid())['name']);
        foreach (self::WRITABLE as $folder) {
            $this->assertIsWritable(base_path($folder), "$folder is not writable");
        }
    }






    // -----------------------------------------------------------
    // withoutEnv
    // -----------------------------------------------------------
    //
    // A config file evaluated with the named variables unset,
    // as in a container started without them; they are put
    // back afterwards.
    //
    // Used by:
    //   - testNodeSettingsDefaultToALocalNode (above)
    //   - PlatformDefectsTest (this folder)
    // -----------------------------------------------------------

    public static function withoutEnv(array $names, string $file): array
    {
        $saved = [];
        foreach ($names as $name) {
            $saved[$name] = getenv($name);
            putenv($name);
        }
        try {
            return require config_path($file);
        } finally {
            foreach ($saved as $name => $value) {
                if ($value !== false) {
                    putenv("$name=$value");
                }
            }
        }
    }
}
