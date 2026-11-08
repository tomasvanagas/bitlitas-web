<?php

// -----------------------------------------------------------
//  [*] BlocksController — the sync's web door
//
//  GET /blokai runs one sync with the full node and answers
//  with what it did, as JSON. The route is public and
//  throttled to ten visits a minute, since every visit
//  queries the node; the command line's bitlitas:sync does
//  the same work without a limit.
//
//  Used by:
//    - routes/web.php — GET /blokai
// -----------------------------------------------------------


namespace App\Http\Controllers;

use App\Services\DaemonException;
use App\Services\NetworkSync;
use Illuminate\Support\Facades\Log;








// -----------------------------------------------------------
// BlocksController
// -----------------------------------------------------------
//
// One action; the throttle and the web group sit in front
// of it in routes/web.php.
//
//   index — one sync, reported as JSON
//
// Used by:
//   - routes/web.php
// -----------------------------------------------------------

class BlocksController extends Controller
{

    // Blocks stored per visit, which keeps one request well
    // inside PHP's time limit; bitlitas:sync has no limit
    private const BLOCKS_PER_REQUEST = 5000;






    // -----------------------------------------------------------
    // index
    // -----------------------------------------------------------
    //
    // GET /blokai
    //
    // Runs NetworkSync for at most BLOCKS_PER_REQUEST blocks and
    // answers with its report: 200 when it ran, 409 while
    // another sync holds the lock, 503 when the node failed.
    // The 503 names the node's error only in debug mode, since
    // the message holds internal addresses; the error goes to
    // Laravel's log as a warning either way.
    //
    // Used by:
    //   - an operator or a cron calling the URL — no cron calls
    //     it at the moment
    // -----------------------------------------------------------

    public function index(NetworkSync $sync)
    {
        try {
            $result = $sync->run(self::BLOCKS_PER_REQUEST);
        } catch (DaemonException $e) {
            Log::warning('Bitlitas node sync failed: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                // The details name internal addresses, so only when debugging
                'error' => config('app.debug') ? $e->getMessage() : 'The full node did not answer',
            ], 503);
        }

        return response()->json($result, $result['status'] === 'busy' ? 409 : 200);
    }
}
