<?php

// -----------------------------------------------------------
//  [*] World — the rows a test builds
//
//  The app reads two tables: blocks, one row per block the
//  sync stored, and statistics, the three figures the home
//  page shows. A test starts from both empty — the schema
//  the migrations build — and lays down what it needs
//  here: the rows seedDB.sh leaves in production, figures of
//  its own, single blocks, or a run of Chain's blocks as a
//  sync would have stored them. The Feature suite's rows go
//  with its transaction; the Server suite's are committed,
//  for Apache's PHP to see, and removed after each test.
//
//  Used by:
//    - TestCase (tests/) — one World per test
//    - ServerTestCase (tests/) — removeAll around every test
// -----------------------------------------------------------


namespace Tests\Support;

use App\Blocks;
use App\Statistic;
use Carbon\Carbon;








// -----------------------------------------------------------
// World
// -----------------------------------------------------------
//
//   seed             — the rows seedDB.sh leaves
//   placeholderBlock — seedDB.sh's stand-in block 0
//   statistics       — figures, stored as the sync stores them
//   block            — one block row
//   chain            — Chain's blocks from to to
//   heights          — the stored heights, in insertion order
//   statistic        — one stored figure
//   removeAll        — both tables emptied
//
// Used by:
//   - TestCase, ServerTestCase (tests/)
//   - the Feature and Server suites
// -----------------------------------------------------------

class World
{






    // -----------------------------------------------------------
    // seed
    // -----------------------------------------------------------
    //
    // What seedDB.sh leaves in a fresh production database:
    // the three figures at 0 and one stand-in block 0 without
    // a reward. The script inserts two stand-ins, but MySQL's
    // strict mode refuses the first, which has no timestamp
    // (SchemaTest::testBlockWithoutATimestampIsRefused), so
    // one is stored.
    //
    // Used by:
    //   - the Feature and Server suites
    // -----------------------------------------------------------

    public function seed(): void
    {
        $this->statistics(['mined' => 0, 'hash_rate' => 0, 'difficulty' => 0]);
        $this->placeholderBlock();
    }






    // -----------------------------------------------------------
    // placeholderBlock
    // -----------------------------------------------------------
    //
    // seedDB.sh's stand-in: block 0, no reward, stamped with
    // the time of the seed, and no fees, which seedDB.sh does
    // not name.
    //
    // Used by:
    //   - seed (above)
    //   - NetworkSyncTest (Feature/Sync/) — two of them, as on
    //     a server without strict mode
    // -----------------------------------------------------------

    public function placeholderBlock(): void
    {
        $this->block(0, 0, time(), null);
    }






    // -----------------------------------------------------------
    // statistics
    // -----------------------------------------------------------
    //
    // Stores each figure under its name, as text, the way
    // the sync does: a new row, or the value of the existing
    // one replaced.
    //
    // Used by:
    //   - seed (above)
    //   - the Feature suite
    // -----------------------------------------------------------

    public function statistics(array $values): void
    {
        foreach ($values as $stat => $value) {
            Statistic::updateOrCreate(['stat' => $stat], ['value' => (string) $value]);
        }
    }






    // -----------------------------------------------------------
    // block
    // -----------------------------------------------------------
    //
    // One block row; the time defaults to Chain's for the
    // height, the fees to none — null for a row stored without
    // them.
    //
    // Used by:
    //   - placeholderBlock (above)
    //   - the Feature suite
    // -----------------------------------------------------------

    public function block(int $height, int $amount, ?int $timestamp = null, ?int $fees = 0): void
    {
        Blocks::create([
            'block_height' => $height,
            'amount'       => $amount,
            'fees'         => $fees,
            'timestamp'    => $timestamp ?? Chain::timestamp($height),
        ]);
    }






    // -----------------------------------------------------------
    // chain
    // -----------------------------------------------------------
    //
    // Chain's blocks $from to $to with their rewards and
    // times, a thousand rows per insert: as a sync stores
    // them, with no fees, since Chain's blocks hold no
    // transaction; or, $withFees false, as rows stored before
    // the table had a fees column, with null there.
    //
    // Used by:
    //   - the Feature and Server suites
    // -----------------------------------------------------------

    public function chain(int $from, int $to, bool $withFees = true): void
    {
        $now = Carbon::now();
        for ($start = $from; $start <= $to; $start += 1000) {
            $rows = [];
            for ($height = $start; $height <= min($to, $start + 999); $height++) {
                $rows[] = [
                    'block_height' => $height,
                    'amount'       => Chain::reward($height),
                    'fees'         => $withFees ? 0 : null,
                    'timestamp'    => Chain::timestamp($height),
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }
            Blocks::insert($rows);
        }
    }






    // -----------------------------------------------------------
    // heights
    // -----------------------------------------------------------
    //
    // Every stored block height, in the order the rows were
    // inserted.
    //
    // Used by:
    //   - the Feature and Server suites
    // -----------------------------------------------------------

    public function heights(): array
    {
        return Blocks::orderBy('id')->pluck('block_height')->map(function ($height) {
            return (int) $height;
        })->all();
    }






    // -----------------------------------------------------------
    // statistic
    // -----------------------------------------------------------
    //
    // The stored value of one figure, or null when it has no
    // row.
    //
    // Used by:
    //   - the Feature and Server suites
    // -----------------------------------------------------------

    public function statistic(string $stat): ?string
    {
        $value = Statistic::where('stat', $stat)->value('value');
        return $value === null ? null : (string) $value;
    }






    // -----------------------------------------------------------
    // removeAll
    // -----------------------------------------------------------
    //
    // Both tables emptied, whoever wrote the rows — the test,
    // or the site syncing on its behalf.
    //
    // Used by:
    //   - ServerTestCase (tests/) — before and after every test
    // -----------------------------------------------------------

    public function removeAll(): void
    {
        Blocks::query()->delete();
        Statistic::query()->delete();
    }
}
