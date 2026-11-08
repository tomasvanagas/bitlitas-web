<?php

// -----------------------------------------------------------
//  [*] Statistic — the home page's named figures
//
//  The Eloquent model of the statistics table: one row per
//  figure, its name in stat and its value as text. The site
//  keeps three — mined (LIT so far, to the hundredth),
//  hash_rate (hashes per second) and difficulty — which
//  seedDB.sh creates at 0 and NetworkSync rewrites on every
//  run.
//
//  Used by:
//    - NetworkSync (app/Services) — writes the three figures
//    - HomeController — reads them for the page
// -----------------------------------------------------------


namespace App;

use Illuminate\Database\Eloquent\Model;








// -----------------------------------------------------------
// Statistic
// -----------------------------------------------------------
//
// Laravel's default table name (statistics) and timestamps.
//
// Used by:
//   - NetworkSync (app/Services), HomeController
// -----------------------------------------------------------

class Statistic extends Model
{

    // The columns create() and updateOrCreate() may fill: the
    // figure's name and its value
    protected $fillable = [
        'stat', 'value'
    ];
}
