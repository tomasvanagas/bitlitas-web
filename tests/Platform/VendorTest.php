<?php

// -----------------------------------------------------------
//  [*] VendorTest — the packages the image ships
//
//  The Dockerfile installs composer.lock with --no-dev, so
//  the vendor folder holds the production packages only: no
//  PHPUnit, no Mockery, no Faker, no Whoops — Whoops' debug
//  page lists the server's environment, secrets included —
//  and no Ignition, whose debug page has had a remote code
//  execution of its own (CVE-2021-3129). Composer's scripts
//  run Laravel's package discovery, which adds the providers
//  of three production packages to the ones config/app.php
//  lists, and of no development one.
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

    // The development and error-page packages a Laravel app
    // may pull in, the app's own require-dev among them; none
    // may reach the image
    private const DEV_PACKAGES = [
        'facade/ignition',
        'fakerphp/faker',
        'filp/whoops',
        'fzaninotto/faker',
        'mockery/mockery',
        'nunomaduro/collision',
        'phpunit/phpunit',
        'spatie/ignition',
        'spatie/laravel-ignition',
        'symfony/thanks',
    ];

    // The packages discovery registers, with their providers:
    // tinker's command, Carbon's locale sync, Termwind's
    // console styles
    private const DISCOVERED = [
        'laravel/tinker'      => ['providers' => ['Laravel\\Tinker\\TinkerServiceProvider']],
        'nesbot/carbon'       => ['providers' => ['Carbon\\Laravel\\ServiceProvider']],
        'nunomaduro/termwind' => ['providers' => ['Termwind\\Laravel\\TermwindServiceProvider']],
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
            $this->assertDirectoryDoesNotExist(base_path("vendor/$package"), "$package is installed");
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
    // Laravel 13.40.0.
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
        $this->assertSame('v13.40.0', $versions['laravel/framework']);
    }






    // -----------------------------------------------------------
    // testDiscoveryRegistersNoDevelopmentProvider
    // -----------------------------------------------------------
    //
    // The package manifest, written by discovery when the
    // image is built, holds exactly the three packages of
    // DISCOVERED; config/app.php adds the framework's
    // providers and the app's four.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testDiscoveryRegistersNoDevelopmentProvider(): void
    {
        $this->assertSame(self::DISCOVERED, require base_path('bootstrap/cache/packages.php'));

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
