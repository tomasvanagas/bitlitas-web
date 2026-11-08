<?php

// -----------------------------------------------------------
//  [*] TestCase — the base of every test in the suite
//
//  Boots the app for every test and gives the test a World
//  to build its rows with, plus the helpers the suites share:
//  a node in memory in place of the real client, the part of
//  Laravel's log a step wrote, and the sync lock held the
//  way a running sync holds it. FeatureTestCase wraps each
//  test in a transaction on top of it; ServerTestCase adds
//  an HTTP client for the real Apache instead.
//
//  Used by:
//    - FeatureTestCase, ServerTestCase (this folder)
//    - the Platform suite (Platform/)
// -----------------------------------------------------------


namespace Tests;

use App\Services\DaemonClient;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Support\FakeDaemon;
use Tests\Support\KnownDefects;
use Tests\Support\World;








// -----------------------------------------------------------
// TestCase
// -----------------------------------------------------------
//
// The shared setup and the helpers every suite leans on.
//
//   setUp               — the fresh app and the World
//   fakeDaemon          — a node in memory, bound in the
//                         container
//   console             — an artisan command, run as the
//                         command line runs it
//   logWrittenBy        — what laravel.log gained during a step
//   whileSyncLockIsHeld — a step run while another sync holds
//                         the lock
//
// Used by:
//   - FeatureTestCase, ServerTestCase (this folder)
//   - the Platform suite (Platform/)
// -----------------------------------------------------------

abstract class TestCase extends LaravelTestCase
{

    // The app for every test, and pinDefect
    use CreatesApplication;
    use KnownDefects;

    // The rows of the test running now are built through it
    protected $world;






    // -----------------------------------------------------------
    // setUp
    // -----------------------------------------------------------
    //
    // A fresh app (Laravel's own setUp) and a fresh World.
    //
    // Used by:
    //   - PHPUnit, before every test
    // -----------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();
        $this->world = new World();
    }






    // -----------------------------------------------------------
    // fakeDaemon
    // -----------------------------------------------------------
    //
    // Binds a FakeDaemon with a node of the given state in
    // place of the app's DaemonClient singleton, for the rest
    // of the test, and returns it.
    //
    // Used by:
    //   - the Feature suite (Feature/Sync/, Feature/Site/)
    // -----------------------------------------------------------

    protected function fakeDaemon(array $state = []): FakeDaemon
    {
        $daemon = new FakeDaemon($state);
        $this->app->instance(DaemonClient::class, $daemon);
        return $daemon;
    }






    // -----------------------------------------------------------
    // console
    // -----------------------------------------------------------
    //
    // Runs an artisan command through the console kernel's
    // handle, with its words in an ArgvInput — the way
    // `php artisan` runs it — and returns the exit code and the
    // printed lines, blank ones left out. Laravel's own
    // artisan() helper hands the command to an ArrayInput and,
    // by default, a mocked output that a test must script in
    // advance; this one runs it as the command line does and
    // keeps what it printed.
    //
    // Used by:
    //   - SyncCommandTest (Feature/Sync/)
    //   - RouteDefectsTest (Feature/Routes/)
    // -----------------------------------------------------------

    protected function console(string ...$words): array
    {
        $output = new BufferedOutput();
        $code = $this->app[Kernel::class]->handle(new ArgvInput(array_merge(['artisan'], $words)), $output);
        $lines = array_values(array_filter(array_map('trim', explode("\n", $output->fetch())), 'strlen'));
        return [$code, $lines];
    }






    // -----------------------------------------------------------
    // logWrittenBy
    // -----------------------------------------------------------
    //
    // Runs the step and returns what storage/logs/laravel.log
    // gained meanwhile — the log the bind mount keeps in
    // production, a tmpfs in the test container.
    //
    // Used by:
    //   - BlocksRouteTest (Feature/Sync/)
    //   - ProductionModeTest, SyncOverHttpTest (Server/)
    // -----------------------------------------------------------

    protected function logWrittenBy(callable $step): string
    {
        $log = storage_path('logs/laravel.log');
        clearstatcache();
        $before = is_file($log) ? filesize($log) : 0;

        $step();

        clearstatcache();
        return is_file($log) ? (string) file_get_contents($log, false, null, $before) : '';
    }






    // -----------------------------------------------------------
    // whileSyncLockIsHeld
    // -----------------------------------------------------------
    //
    // Runs the step while this process holds the lock file a
    // running sync holds — the same file NetworkSync locks, so
    // the app sees a second sync — and returns the step's
    // result.
    //
    // Used by:
    //   - NetworkSyncTest, BlocksRouteTest, SyncCommandTest
    //     (Feature/Sync/)
    //   - SyncOverHttpTest (Server/)
    // -----------------------------------------------------------

    protected function whileSyncLockIsHeld(callable $step)
    {
        $lock = fopen(storage_path('framework/bitlitas-sync.lock'), 'c');
        $this->assertTrue(flock($lock, LOCK_EX | LOCK_NB), 'the sync lock is already held');
        try {
            return $step();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
