<?php

namespace App\Services;

use App\Blocks;
use App\Statistic;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Copies block rewards from the full node into the `blocks` table and
 * refreshes the `statistics` the home page shows: mined (LIT so far),
 * hash_rate and difficulty. The 2018 code did the same through the
 * block explorer's API.
 */
class NetworkSync
{
    /** Headers per RPC call (restricted v0.18 nodes refuse more than 1001) */
    const HEADERS_PER_CALL = 1000;

    /** Atomic units in one LIT */
    const ATOMIC_UNITS = 1000000000000;

    /** @var DaemonClient */
    private $daemon;

    public function __construct(DaemonClient $daemon)
    {
        $this->daemon = $daemon;
    }

    /**
     * Store the blocks the node has and the table doesn't (at most
     * $maxBlocks of them, oldest first), then recompute the statistics.
     *
     * @param  int|null $maxBlocks  null: all the way to the node's top block
     * @return array  status "ok" with the heights and the new statistics,
     *                or status "busy" when another sync is running
     *
     * @throws DaemonException  the node is unreachable or answered nonsense
     *                          (the batches stored before that are kept)
     */
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

    private function sync($maxBlocks)
    {
        $info = $this->daemon->getInfo();
        $top = (int) $info['height'] - 1;

        $this->replaceSeededGenesis();

        $from = $this->nextHeight();
        $to = $maxBlocks === null ? $top : min($top, $from + (int) $maxBlocks - 1);

        for ($start = $from; $start <= $to; $start += self::HEADERS_PER_CALL) {
            $end = min($to, $start + self::HEADERS_PER_CALL - 1);
            $this->store($start, $end, $this->daemon->getBlockHeadersRange($start, $end));
        }

        $lastStored = max($to, $from - 1);

        return array_merge([
            'status' => 'ok',
            'fetched' => max(0, $to - $from + 1),
            'last_stored_block' => $lastStored,
            'node_top_block' => $top,
            'remaining' => max(0, $top - $lastStored),
        ], $this->saveStatistics($info));
    }

    /**
     * seedDB.sh stores a stand-in block 0 without a reward so the home
     * page has a "last block" before the first sync. Swap it for the
     * real genesis block, whose reward counts toward the mined total.
     */
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

    /**
     * Insert blocks $start to $end in one statement, so a failed run
     * never leaves part of a batch behind, after checking the node sent
     * exactly those blocks, in order.
     */
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
                'amount' => $header['reward'],
                'timestamp' => $header['timestamp'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $height++;
        }

        if ($height !== $end + 1) {
            throw new DaemonException("get_block_headers_range: asked for blocks $start-$end, got " . count($headers));
        }

        Blocks::insert($rows);
    }

    /** The first block the table doesn't have yet */
    private function nextHeight()
    {
        $stored = Blocks::max('block_height');

        return $stored === null ? 0 : (int) $stored + 1;
    }

    /**
     * Recompute and store the home page's figures; returns them.
     */
    private function saveStatistics(array $info)
    {
        $difficulty = (int) $info['difficulty'];
        $target = (int) $info['target'];

        $statistics = [
            'mined' => round(Blocks::sum('amount') / self::ATOMIC_UNITS, 2),
            // Hashes per second, worked out the way the 2018 explorer did
            'hash_rate' => $target > 0 ? intdiv($difficulty, $target) : 0,
            'difficulty' => $difficulty,
        ];

        foreach ($statistics as $stat => $value) {
            Statistic::updateOrCreate(['stat' => $stat], ['value' => (string) $value]);
        }

        return $statistics;
    }

    /**
     * An exclusive lock, so a cron run and a visitor can't store the
     * same blocks twice; null while another sync holds it.
     *
     * @return resource|null
     */
    private function lock()
    {
        $path = storage_path('framework/bitlitas-sync.lock');
        $handle = fopen($path, 'c');

        // Shared by php-fpm (www-data) and artisan (often run as root)
        @chmod($path, 0666);

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return $handle;
    }
}
