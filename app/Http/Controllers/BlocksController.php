<?php

namespace App\Http\Controllers;

use App\Services\DaemonException;
use App\Services\NetworkSync;
use Illuminate\Support\Facades\Log;

class BlocksController extends Controller
{
    /**
     * Blocks stored per visit, which keeps one request well inside
     * PHP's time limit; `php artisan bitlitas:sync` has no limit.
     */
    const BLOCKS_PER_REQUEST = 5000;

    /**
     * GET /blokai: bring the blocks and statistics up to date with the
     * full node and report how far that got, as JSON.
     *
     * @param  NetworkSync $sync
     * @return \Illuminate\Http\JsonResponse
     */
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
