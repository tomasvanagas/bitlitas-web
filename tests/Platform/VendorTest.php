<?php

// -----------------------------------------------------------
//  [*] VendorTest — the packages the image ships
//
//  The Dockerfile installs composer.lock with --no-dev, so
//  the vendor folder holds the production packages only: no
//  PHPUnit, no Mockery, no Faker, no Whoops — Whoops' debug
//  page lists the server's environment, secrets included.
//  It also writes an empty package manifest and skips
//  composer's scripts, because Laravel 5.5's package
//  discovery cannot read the installed.json Composer 2
//  writes: the app runs only the providers config/app.php
//  lists.
//
//  Used by:
//    - PHPUnit — the Platform suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Platform;

use Tests\TestCase;








// -----------------------------------------------------------
// VendorTest
// -----------------------------------------------------------
//
// Reads vendor/ and the composer metadata of the app — the
// test tools live in /opt/test-tools, outside it.
//
// Used by:
//   - PHPUnit — the Platform suite (runTests.sh)
// -----------------------------------------------------------

class VendorTest extends TestCase
{

    // The development packages of composer.json, which must
    // never reach the image
    private const DEV_PACKAGES = [
        'filp/whoops',
        'fzaninotto/faker',
        'mockery/mockery',
        'phpunit/phpunit',
        'symfony/thanks',
    ];






    // -----------------------------------------------------------
    // testNoDevelopmentPackageIsInstalled
    // -----------------------------------------------------------
    //
    // None of the development packages has a folder in
    // vendor/, and composer recorded the install as one
    // without development packages.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testNoDevelopmentPackageIsInstalled(): void
    {
        foreach (self::DEV_PACKAGES as $package) {
            $this->assertDirectoryNotExists(base_path("vendor/$package"), "$package is installed");
        }

        $installed = json_decode(file_get_contents(base_path('vendor/composer/installed.json')), true);
        $this->assertFalse($installed['dev']);
        $names = array_column($installed['packages'], 'name');
        $this->assertSame([], array_values(array_intersect(self::DEV_PACKAGES, $names)));
    }






    // -----------------------------------------------------------
    // testInstalledVersionsAreTheLockedOnes
    // -----------------------------------------------------------
    //
    // Every package composer.lock pins is installed at exactly
    // the locked version — the image is built from the lock,
    // never from fresh resolution — and the framework is
    // Laravel 5.5.33.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testInstalledVersionsAreTheLockedOnes(): void
    {
        $lock = json_decode(file_get_contents(base_path('composer.lock')), true);
        $installed = json_decode(file_get_contents(base_path('vendor/composer/installed.json')), true);
        $versions = array_column($installed['packages'], 'version', 'name');

        foreach ($lock['packages'] as $package) {
            $this->assertSame($package['version'], $versions[$package['name']] ?? null, $package['name']);
        }
        $this->assertSame('v5.5.33', $versions['laravel/framework']);
    }






    // -----------------------------------------------------------
    // testPackageDiscoveryIsSwitchedOff
    // -----------------------------------------------------------
    //
    // The package manifest is the empty one the Dockerfile
    // writes, so only config/app.php's providers run: the
    // framework's own and the app's four.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testPackageDiscoveryIsSwitchedOff(): void
    {
        $this->assertSame([], require base_path('bootstrap/cache/packages.php'));

        $own = array_values(array_filter(config('app.providers'), function (string $provider) {
            return strpos($provider, 'Illuminate\\') !== 0;
        }));
        $this->assertSame([
            'App\\Providers\\AppServiceProvider',
            'App\\Providers\\AuthServiceProvider',
            'App\\Providers\\EventServiceProvider',
            'App\\Providers\\RouteServiceProvider',
        ], $own);
    }
}
