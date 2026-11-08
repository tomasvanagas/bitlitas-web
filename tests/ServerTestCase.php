<?php

// -----------------------------------------------------------
//  [*] ServerTestCase — the base of the tests over HTTP
//
//  TestCase with a client for the real Apache that run.sh
//  starts on port 8080. Apache runs the app in processes of
//  its own, which see only committed rows, so these tests
//  get no transaction: the rows a test builds through its
//  World are committed, and both tables are emptied again
//  around the test. The stand-in node starts each test
//  fresh, and so does the sync route's throttle, whose
//  counters the app keeps in Laravel's file cache.
//
//  Used by:
//    - the Server suite (Server/)
// -----------------------------------------------------------


namespace Tests;

use Illuminate\Filesystem\Filesystem;
use Tests\Support\FakeNodeServer;
use Tests\Support\ServerClient;








// -----------------------------------------------------------
// ServerTestCase
// -----------------------------------------------------------
//
// One client per test.
//
//   setUp    — the client, empty tables, a fresh node and
//              throttle
//   tearDown — empty tables and a fresh node again
//
// Used by:
//   - the Server suite (Server/)
// -----------------------------------------------------------

abstract class ServerTestCase extends TestCase
{

    // The client of the test running now
    protected $http;






    // -----------------------------------------------------------
    // setUp
    // -----------------------------------------------------------
    //
    // TestCase's setup, then a client, both tables empty, the
    // default node and no throttle counters.
    //
    // Used by:
    //   - PHPUnit, before every test
    // -----------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();
        $this->http = new ServerClient();
        $this->world->removeAll();
        FakeNodeServer::reset();
        if (is_dir(storage_path('framework/cache/data'))) {
            (new Filesystem())->cleanDirectory(storage_path('framework/cache/data'));
        }
    }






    // -----------------------------------------------------------
    // tearDown
    // -----------------------------------------------------------
    //
    // Empties both tables and resets the node before Laravel
    // throws the app away.
    //
    // Used by:
    //   - PHPUnit, after every test
    // -----------------------------------------------------------

    protected function tearDown(): void
    {
        $this->world->removeAll();
        FakeNodeServer::reset();
        parent::tearDown();
    }
}
