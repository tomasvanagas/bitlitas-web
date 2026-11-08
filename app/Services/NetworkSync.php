<?php

// -----------------------------------------------------------
//  [*] NetworkSync — copying the chain from the full node
//
//  Brings the blocks table and the three figures of the
//  statistics table up to date with the full node. It
//  stores the blocks the table lacks, oldest first, from the
//  headers of get_block_headers_range, each with the fees
//  its transactions paid, then recomputes the mined coins,
//  the hash rate and the difficulty. The mined figure counts
//  the coins the chain made: a block's reward is its new
//  coins and its fees together, and the fees are taken out.
//  Rows stored before the table had a fees column get their
//  fees filled in first, oldest first. A node lower than the
//  table — one syncing from scratch — is reported as behind
//  and changes nothing. A lock file lets one sync run at a
//  time, whether a visit or the command line started it.
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
//   fillMissingFees      — fees for rows stored without them
//   fetchChecked         — one batch of headers, checked
//   fees                 — the fees of a batch's blocks
//   store                — one batch of blocks in
//   nextHeight           — where the table ends
//   rowsWithoutFees      — how many rows still lack fees
//   saveStatistics       — the home page's figures
//   lock                 — the lock file
//
// Used by:
//   - BlocksController, routes/console.php
// -----------------------------------------------------------

class NetworkSync
{

    // Headers asked for per RPC call: the most a restricted
    // node gives in one, with room to spare
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
    // One sync of at most $maxBlocks blocks — rows whose fees
    // it fills in and new blocks together; null for all of
    // them up to the node's top block — under the lock.
    // Returns status "ok" with the heights and the new
    // figures, status "behind" when the node is lower than the
    // table, or status "busy" without asking the node anything
    // when another sync holds the lock. A DaemonException goes
    // on to the caller; the batches stored before it are kept,
    // and the lock is let go either way.
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
    // view first, so a node that fails changes nothing; a node
    // lower than the table stops the run there, "behind", with
    // nothing asked beyond get_info, nothing stored and the
    // figures left as they were. Otherwise the stand-in block
    // 0 is swapped for the real one, the rows without fees get
    // theirs, the missing blocks follow in batches of
    // HEADERS_PER_CALL with what is left of $maxBlocks, and
    // then the figures. remaining counts both the blocks still
    // missing and the rows still without fees.
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
        $stored = $this->nextHeight() - 1;

        if ($top < $stored) {
            return [
                'status'            => 'behind',
                'fetched'           => 0,
                'filled'            => 0,
                'last_stored_block' => $stored,
                'node_top_block'    => $top,
                'remaining'         => 0,
            ];
        }

        $this->replaceSeededGenesis();


        // STEP 2: the fees of rows stored without them
        // ============================================
        $filled = $this->fillMissingFees($top, $maxBlocks);


        // STEP 3: the missing blocks, a batch per RPC call
        // ================================================
        $from = $this->nextHeight();
        $to = $maxBlocks === null ? $top : min($top, $from + (int) $maxBlocks - $filled - 1);

        for ($start = $from; $start <= $to; $start += self::HEADERS_PER_CALL) {
            $headers = $this->fetchChecked($start, min($to, $start + self::HEADERS_PER_CALL - 1));
            $this->store($headers, $this->fees($headers));
        }


        // STEP 4: the report, with the figures it stored
        // ==============================================
        $lastStored = max($to, $from - 1);

        return array_merge([
            'status'            => 'ok',
            'fetched'           => max(0, $to - $from + 1),
            'filled'            => $filled,
            'last_stored_block' => $lastStored,
            'node_top_block'    => $top,
            'remaining'         => max(0, $top - $lastStored) + $this->rowsWithoutFees($top),
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

        $genesis = $this->fetchChecked(0, 0);
        $fees = $this->fees($genesis);

        DB::transaction(function () use ($genesis, $fees) {
            Blocks::where('block_height', 0)->delete();
            $this->store($genesis, $fees);
        });
    }






    // -----------------------------------------------------------
    // fillMissingFees
    // -----------------------------------------------------------
    //
    // Fills in the fees of rows stored before the table had a
    // fees column — null there — oldest first and at most
    // $maxBlocks of them (null for all), and returns how many
    // it filled. Each pass takes the headers of a run starting
    // at the lowest such row, asks the node for the fees of
    // the blocks that hold transactions, and stores 0 for
    // every other block of the run, in one transaction. A row
    // above the node's top block waits for the node.
    //
    // Used by:
    //   - sync (above)
    // -----------------------------------------------------------

    private function fillMissingFees($top, $maxBlocks)
    {
        $filled = 0;

        while ($maxBlocks === null || $filled < $maxBlocks) {
            $room = $maxBlocks === null ? self::HEADERS_PER_CALL : min(self::HEADERS_PER_CALL, $maxBlocks - $filled);
            $heights = Blocks::whereNull('fees')->where('block_height', '<=', $top)
                ->distinct()->orderBy('block_height')->limit($room)->pluck('block_height')->all();

            if ($heights === []) {
                break;
            }

            $start = (int) $heights[0];
            $end = min((int) end($heights), $start + self::HEADERS_PER_CALL - 1);
            $fees = $this->fees($this->fetchChecked($start, $end));
            $paying = array_keys(array_filter($fees));

            DB::transaction(function () use ($start, $end, $fees, $paying) {
                Blocks::whereNull('fees')->whereBetween('block_height', [$start, $end])
                    ->whereNotIn('block_height', $paying)->update(['fees' => 0]);

                foreach ($paying as $height) {
                    Blocks::whereNull('fees')->where('block_height', $height)->update(['fees' => $fees[$height]]);
                }
            });

            $filled += count(array_filter($heights, function ($height) use ($end) {
                return $height <= $end;
            }));
        }

        return $filled;
    }






    // -----------------------------------------------------------
    // fetchChecked
    // -----------------------------------------------------------
    //
    // The headers of blocks $start to $end, after checking that
    // the node sent exactly those blocks in order: a header
    // with a wrong or missing height, reward, time or count of
    // transactions, or one too few or too many, is a
    // DaemonException.
    //
    // Used by:
    //   - sync, replaceSeededGenesis, fillMissingFees (above)
    // -----------------------------------------------------------

    private function fetchChecked($start, $end)
    {
        $headers = $this->daemon->getBlockHeadersRange($start, $end);
        $height = $start;

        foreach ($headers as $header) {
            if (!isset($header['height'], $header['reward'], $header['timestamp'], $header['num_txes'])
                || (int) $header['height'] !== $height) {
                throw new DaemonException("get_block_headers_range: expected block $height, got something else");
            }

            $height++;
        }

        if ($height !== $end + 1) {
            throw new DaemonException("get_block_headers_range: asked for blocks $start-$end, got " . count($headers));
        }

        return $headers;
    }






    // -----------------------------------------------------------
    // fees
    // -----------------------------------------------------------
    //
    // The fees each block of a checked batch collected, in
    // atomic units, by height: 0 for a block that holds no
    // transaction, which is most of them and costs no call;
    // for the others, the hashes of their transactions from
    // get_block, then all the batch's fees from
    // get_transactions together. A block whose transactions
    // are not the number its header gives, or whose fees come
    // to more than its reward, is a DaemonException.
    //
    // Used by:
    //   - sync, replaceSeededGenesis, fillMissingFees (above)
    // -----------------------------------------------------------

    private function fees(array $headers)
    {
        $fees = [];
        $blockOf = [];

        foreach ($headers as $header) {
            $height = (int) $header['height'];
            $fees[$height] = 0;

            if ((int) $header['num_txes'] > 0) {
                $hashes = $this->daemon->getBlockTransactions($height);

                if (count($hashes) !== (int) $header['num_txes']) {
                    throw new DaemonException("get_block: block $height lists " . count($hashes) . " transactions, its header {$header['num_txes']}");
                }

                foreach ($hashes as $hash) {
                    $blockOf[$hash] = $height;
                }
            }
        }

        if ($blockOf !== []) {
            foreach ($this->daemon->getTransactionFees(array_keys($blockOf)) as $hash => $fee) {
                $fees[$blockOf[$hash]] += $fee;
            }
        }

        foreach ($headers as $header) {
            if ($fees[(int) $header['height']] > (int) $header['reward']) {
                throw new DaemonException("get_transactions: the fees of block {$header['height']} come to more than its reward");
            }
        }

        return $fees;
    }






    // -----------------------------------------------------------
    // store
    // -----------------------------------------------------------
    //
    // Inserts a checked batch of blocks in one statement, each
    // with its reward, time and fees, so a failed run never
    // leaves part of a batch behind.
    //
    // Used by:
    //   - sync, replaceSeededGenesis (above)
    // -----------------------------------------------------------

    private function store(array $headers, array $fees)
    {
        $now = Carbon::now();
        $rows = [];

        foreach ($headers as $header) {
            $height = (int) $header['height'];

            $rows[] = [
                'block_height' => $height,
                'amount'       => $header['reward'],
                'fees'         => $fees[$height],
                'timestamp'    => $header['timestamp'],
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
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
    // rowsWithoutFees
    // -----------------------------------------------------------
    //
    // How many blocks up to $top are stored without fees, each
    // height counted once.
    //
    // Used by:
    //   - sync (above)
    // -----------------------------------------------------------

    private function rowsWithoutFees($top)
    {
        return Blocks::whereNull('fees')->where('block_height', '<=', $top)->distinct()->count('block_height');
    }






    // -----------------------------------------------------------
    // saveStatistics
    // -----------------------------------------------------------
    //
    // Recomputes the home page's figures from the table and
    // get_info, stores each as text — a new row, or the value
    // of the existing one replaced — and returns them: the
    // coins the stored blocks made, in LIT to the hundredth —
    // their rewards less their fees, a row still without fees
    // counted whole — the hash rate as the difficulty over the
    // block time (0 when the node gives no block time), and
    // the difficulty.
    //
    // Used by:
    //   - sync (above)
    // -----------------------------------------------------------

    private function saveStatistics(array $info)
    {
        $difficulty = (int) $info['difficulty'];
        $target = (int) $info['target'];

        $statistics = [
            'mined' => round(Blocks::sum(DB::raw('amount - COALESCE(fees, 0)')) / self::ATOMIC_UNITS, 2),
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
    // holds it. The site and bitlitas:sync both run as the
    // container's user, 1000, but a sync started as another
    // one — docker exec -u root — must neither lock the site
    // out nor be locked out by it. The file is opened to every
    // user; and since storage/framework is a sticky folder
    // anyone may write to, the host's fs.protected_regular
    // refuses user 1000 an fopen 'c' of a lock file root made
    // there, whatever its mode — the file is then opened 'r',
    // and flock locks it all the same.
    //
    // Used by:
    //   - run (above)
    // -----------------------------------------------------------

    private function lock()
    {
        $path = storage_path('framework/bitlitas-sync.lock');
        $handle = @fopen($path, 'c');

        if ($handle === false) {
            $handle = fopen($path, 'r');
        }

        // Shared by the site and every artisan run, whichever user starts it
        @chmod($path, 0666);

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return $handle;
    }
}
