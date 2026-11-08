<?php

// -----------------------------------------------------------
//  [*] KnownDefects — pins a defect the app still has
//
//  The house rule for a defect found but not yet fixed: its
//  regression test is written up front, states the WANTED
//  behaviour, and stays in the suite while the bug lives.
//  pinDefect turns such a test inside out: while the defect
//  reproduces, the failing check marks the test incomplete,
//  tagged with the defect's id, so the run stays green and
//  the report lists every pinned defect after the run
//  (verbose in phpunit.xml); the day the check passes, the
//  test fails and says so — the cue to remove the pin and
//  move the test into its home file. Only an assertion
//  failure counts as the defect: any other error (a broken
//  test, a missing fixture) still errors.
//
//  Used by:
//    - TestCase (tests/)
// -----------------------------------------------------------


namespace Tests\Support;

use PHPUnit\Framework\AssertionFailedError;








// -----------------------------------------------------------
// KnownDefects
// -----------------------------------------------------------
//
// Mixed into TestCase, so every test in the suite can pin
// a defect.
//
// Used by:
//   - TestCase (tests/)
// -----------------------------------------------------------

trait KnownDefects
{






    // -----------------------------------------------------------
    // pinDefect
    // -----------------------------------------------------------
    //
    // Runs the check — a closure asserting the wanted
    // behaviour — for the defect with the given id. A failing
    // check marks the test incomplete with the id and the
    // first line of the failure; a passing one fails the test.
    //
    // Used by:
    //   - the *DefectsTest.php of every suite folder
    //     (tests/README.md lists the pins)
    // -----------------------------------------------------------

    protected function pinDefect(string $id, callable $check): void
    {
        try {
            $check();
        } catch (AssertionFailedError $failure) {
            $this->markTestIncomplete(
                "known defect [$id] — " . strtok($failure->getMessage(), "\n")
            );
        }
        $this->fail("known defect [$id] no longer reproduces: remove the pin and move the test into its home file");
    }
}
