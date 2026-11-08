<?php

// -----------------------------------------------------------
//  [*] NetworkSync — copying the chain from the full node
//
//  Brings the blocks table and the three figures of the
//  statistics table up to date with the full node. It
//  stores the blocks the table lacks, oldest first, from the
//  headers of get_block_headers_range, then recomputes the
//  mined coins, the hash rate and the difficulty. A lock
//  file lets one sync run at a time, whether php-fpm or the
//  command line started it.
//
//  Blocks stored before a reorganization of the chain keep
//  their old rewards and times: the table keeps no hashes to
//  notice one. The two branches' rewards differ only by fees.
//
//  Used by:
//    - BlocksController — GET /blokai, at most 5,000 blocks a
//      visit
//    - routes/console.php — bitlitas:sync, 10,000 a run until
//      the top
// -----------------------------------------------------------


namespace App\Services;

use App\Blocks;
use App\Statistic;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;








// -----------------------------------------------------------
// NetworkSync
// -----------------------------------------------------------
//
// Built by the container with the app's DaemonClient; holds
// no state between runs — where to resume is read from the
// table every time.
//
//   __construct          — the node client
//   run                  — one sync under the lock
//   sync                 — the sync itself
//   replaceSeededGenesis — seedDB.sh's stand-in block 0 out
//   store                — one batch of blocks in
//   nextHeight           — where the table ends
//   saveStatistics       — the home page's figures
//   lock                 — the lock file
//
// Used by:
//   - BlocksController, routes/console.php
// -----------------------------------------------------------

class NetworkSync
{

    // Headers asked for per RPC call: the most a restricted
    // v0.18 node gives in one, with room to spare
    private const HEADERS_PER_CALL = 1000;

    // Atomic units in one LIT
    private const ATOMIC_UNITS = 1000000000000;

    // The full node's client
    private $daemon;






    // -----------------------------------------------------------
    // __construct
    // -----------------------------------------------------------
    //
    // A sync against the given node client.
    //
    // Used by:
    //   - Laravel's container — for BlocksController::index and
    //     the bitlitas:sync closure
    // -----------------------------------------------------------

    public function __construct(DaemonClient $daemon)
    {
        $this->daemon = $daemon;
    }






    // -----------------------------------------------------------
    // run
    // -----------------------------------------------------------
    //
    // One sync of at most $maxBlocks blocks — null for all of
    // them up to the node's top block — under the lock. Returns
    // status "ok" with the heights and the new figures, or
    // status "busy" without asking the node anything when
    // another sync holds the lock. A DaemonException goes on to
    // the caller; the batches stored before it are kept, and
    // the lock is let go either way.
    //
    // Used by:
    //   - BlocksController::index — 5,000 a visit
    //   - the bitlitas:sync closure (routes/console.php) —
    //     10,000 a run
    // -----------------------------------------------------------

    public function run($maxBlocks = null)
    {
        $lock = $this->lock();

        if ($lock === null) {
            return ['status' => 'busy'];
        }

        try {
            return $this->sync($maxBlocks);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }






    // -----------------------------------------------------------
    // sync
    // -----------------------------------------------------------
    //
    // The sync proper, run while the lock is held: the node's
    // view first, so a node that fails changes nothing; then
    // the stand-in block 0 swapped for the real one; then the
    // missing blocks in batches of HEADERS_PER_CALL; then the
    // figures. A node lower than the table gets nothing asked
    // beyond get_info, and nothing stored is deleted.
    //
    // Used by:
    //   - run (above)
    // -----------------------------------------------------------

    private function sync($maxBlocks)
    {
        // STEP 1: where the node and the table stand
        // ==========================================
        $info = $this->daemon->getInfo();
        $top = (int) $info['height'] - 1;

        $this->replaceSeededGenesis();

        $from = $this->nextHeight();
        $to = $maxBlocks === null ? $top : min($top, $from + (int) $maxBlocks - 1);


        // STEP 2: the missing blocks, a batch per RPC call
        // ================================================
        for ($start = $from; $start <= $to; $start += self::HEADERS_PER_CALL) {
            $end = min($to, $start + self::HEADERS_PER_CALL - 1);
            $this->store($start, $end, $this->daemon->getBlockHeadersRange($start, $end));
        }


        // STEP 3: the report, with the figures it stored
        // ==============================================
        $lastStored = max($to, $from - 1);

        return array_merge([
            'status'            => 'ok',
            'fetched'           => max(0, $to - $from + 1),
            'last_stored_block' => $lastStored,
            'node_top_block'    => $top,
            'remaining'         => max(0, $top - $lastStored),
        ], $this->saveStatistics($info));
    }






    // -----------------------------------------------------------
    // replaceSeededGenesis
    // -----------------------------------------------------------
    //
    // seedDB.sh stores a stand-in block 0 without a reward, so
    // the home page has a last block before the first sync. A
    // block 0 whose rewards are all 0 is that stand-in — the
    // real one pays 17.592186044415 LIT — and gives way to the
    // real block 0, whose reward counts toward the mined total;
    // two stand-ins, as a server without strict mode keeps,
    // both go. The real header is fetched first and the swap
    // runs in a transaction, so a node failure leaves the
    // stand-in in place.
    //
    // Used by:
    //   - sync (above)
    // -----------------------------------------------------------

    private function replaceSeededGenesis()
    {
        $rewards = Blocks::where('block_height', 0)->pluck('amount');

        // Nothing stored yet (the sync then starts at block 0), or already real
        if ($rewards->isEmpty() || $rewards->max() > 0) {
            return;
        }

        $genesis = $this->daemon->getBlockHeadersRange(0, 0);

        DB::transaction(function () use ($genesis) {
            Blocks::where('block_height', 0)->delete();
            $this->store(0, 0, $genesis);
        });
    }






    // -----------------------------------------------------------
    // store
    // -----------------------------------------------------------
    //
    // Inserts blocks $start to $end in one statement, so a
    // failed run never leaves part of a batch behind, after
    // checking that the node sent exactly those blocks in
    // order: a header with a wrong or missing height, reward
    // or time, or one too few or too many, is a
    // DaemonException and nothing of the batch is stored.
    //
    // Used by:
    //   - sync, replaceSeededGenesis (above)
    // -----------------------------------------------------------

    private function store($start, $end, array $headers)
    {
        $now = Carbon::now();
        $rows = [];
        $height = $start;

        foreach ($headers as $header) {
            if (!isset($header['height'], $header['reward'], $header['timestamp'])
                || (int) $header['height'] !== $height) {
                throw new DaemonException("get_block_headers_range: expected block $height, got something else");
            }

            $rows[] = [
                'block_height' => $height,
                'amount'       => $header['reward'],
                'timestamp'    => $header['timestamp'],
                'created_at'   => $now,
                'updated_at'   => $now,
            ];

            $height++;
        }

        if ($height !== $end + 1) {
            throw new DaemonException("get_block_headers_range: asked for blocks $start-$end, got " . count($headers));
        }

        Blocks::insert($rows);
    }






    // -----------------------------------------------------------
    // nextHeight
    // -----------------------------------------------------------
    //
    // The first block the table does not have: one past the
    // highest stored height, or 0 on an empty table.
    //
    // Used by:
    //   - sync (above)
    // -----------------------------------------------------------

    private function nextHeight()
    {
        $stored = Blocks::max('block_height');

        return $stored === null ? 0 : (int) $stored + 1;
    }






    // -----------------------------------------------------------
    // saveStatistics
    // -----------------------------------------------------------
    //
    // Recomputes the home page's figures from the table and
    // get_info, stores each as text — a new row, or the value
    // of the existing one replaced — and returns them: the
    // coins of every stored block in LIT to the hundredth, the
    // hash rate as the difficulty over the block time (0 when
    // the node gives no block time), and the difficulty.
    //
    // Used by:
    //   - sync (above)
    // -----------------------------------------------------------

    private function saveStatistics(array $info)
    {
        $difficulty = (int) $info['difficulty'];
        $target = (int) $info['target'];

        $statistics = [
            'mined' => round(Blocks::sum('amount') / self::ATOMIC_UNITS, 2),
            // Hashes per second: a block's expected hashes over the seconds between blocks
            'hash_rate'  => $target > 0 ? intdiv($difficulty, $target) : 0,
            'difficulty' => $difficulty,
        ];

        foreach ($statistics as $stat => $value) {
            Statistic::updateOrCreate(['stat' => $stat], ['value' => (string) $value]);
        }

        return $statistics;
    }






    // -----------------------------------------------------------
    // lock
    // -----------------------------------------------------------
    //
    // An exclusive lock on storage/framework/bitlitas-sync.lock,
    // taken without waiting, so a cron run and a visitor cannot
    // store the same blocks twice; null while another sync
    // holds it. The file is opened to every user: php-fpm and
    // bitlitas:sync both run as the container's user, 1000,
    // but a sync started as another one — docker exec -u root
    // — must neither lock php-fpm out nor be locked out by it.
    //
    // Used by:
    //   - run (above)
    // -----------------------------------------------------------

    private function lock()
    {
        $path = storage_path('framework/bitlitas-sync.lock');
        $handle = fopen($path, 'c');

        // Shared by php-fpm and every artisan run, whichever user starts it
        @chmod($path, 0666);

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return $handle;
    }
}
