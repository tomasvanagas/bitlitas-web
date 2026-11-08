<?php

// -----------------------------------------------------------
//  [*] Blocks — one row per block the sync stored
//
//  The Eloquent model of the blocks table: a block's height,
//  its time, the reward its miner received in atomic units
//  (a LIT is 10^12 of them) and the part of that reward its
//  transactions paid in fees. NetworkSync fills it from the
//  full node, oldest block first, so the row with the highest
//  id is the top block the home page shows; the table keeps
//  no hashes and no key on the height.
//
//  Used by:
//    - NetworkSync (app/Services) — stores the rows, sums the
//      rewards, finds where to resume
//    - HomeController — the last stored block
// -----------------------------------------------------------


namespace App;

use Illuminate\Database\Eloquent\Model;








// -----------------------------------------------------------
// Blocks
// -----------------------------------------------------------
//
// Laravel's default table name (blocks) and timestamps;
// created_at and updated_at say when the sync stored a row,
// timestamp when the block was mined.
//
// Used by:
//   - NetworkSync (app/Services), HomeController
// -----------------------------------------------------------

class Blocks extends Model
{

    // The columns create() may fill: the block's height, its
    // reward and fees in atomic units, and its time
    protected $fillable = [
        'block_height', 'amount', 'fees', 'timestamp'
    ];
}
