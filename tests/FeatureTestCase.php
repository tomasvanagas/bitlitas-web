<?php

// -----------------------------------------------------------
//  [*] FeatureTestCase — the base of the in-process tests
//
//  TestCase with every test inside a database transaction
//  that is rolled back afterwards, so each test starts from
//  the empty tables the migrations build and leaves nothing
//  behind. Auto-increment counters do move on — no test may
//  assume an id.
//
//  Used by:
//    - the Feature suite (Feature/)
// -----------------------------------------------------------


namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;








// -----------------------------------------------------------
// FeatureTestCase
// -----------------------------------------------------------
//
// DatabaseTransactions opens the transaction on the default
// connection before each test and rolls it back after; the
// app runs on that same connection, so its writes go too —
// the sync's own transaction becomes a savepoint inside it.
//
// Used by:
//   - the Feature suite (Feature/)
// -----------------------------------------------------------

abstract class FeatureTestCase extends TestCase
{

    // A transaction around every test, rolled back after it
    use DatabaseTransactions;
}
