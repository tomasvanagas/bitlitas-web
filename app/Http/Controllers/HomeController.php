<?php

// -----------------------------------------------------------
//  [*] HomeController — the site's one page
//
//  Every visit to bitlitas.lt lands here: the home page,
//  open to anyone, with the network's figures as the last
//  sync stored them. The controller only reads and shortens
//  them; NetworkSync is what keeps them current.
//
//  Used by:
//    - routes/web.php — GET /
// -----------------------------------------------------------


namespace App\Http\Controllers;

use App\Statistic;
use App\Blocks;








// -----------------------------------------------------------
// HomeController
// -----------------------------------------------------------
//
// One action, no middleware of its own beyond the web group.
//
//   index — the home page
//
// Used by:
//   - routes/web.php
// -----------------------------------------------------------

class HomeController extends Controller
{






    // -----------------------------------------------------------
    // index
    // -----------------------------------------------------------
    //
    // GET /
    //
    // Renders welcome.blade.php with the three figures of
    // statistics and the last stored block: the coins mined,
    // cut to whole thousands ("tūkst.") or millions ("mln."),
    // and as a share of the 18.3 million the page names; the
    // hash rate in H/s, cut to whole kH/s or MH/s; the
    // difficulty, which the view groups in thousands; the
    // height of the block with the highest id and its reward
    // in LIT. A figure or a block the database does not hold
    // yet — before seedDB.sh or the first sync — counts as 0,
    // so the page opens on an empty database too. The steps
    // are 1,000 and 1,000,000 themselves: the mined figure
    // has decimals, and 999.5 coins are still under a
    // thousand.
    //
    // Used by:
    //   - the visitor's browser — every visit to the site
    // -----------------------------------------------------------

    public function index()
    {
        // STEP 1: the three figures and the top stored block,
        // 0 for whatever is not stored yet
        // ===================================================
        $statistics = Statistic::get();
        $money = $statistics->where('stat', '=', 'mined')->first()?->value ?? 0;
        $hashRate = $statistics->where('stat', '=', 'hash_rate')->first()?->value ?? 0;
        $difficulty = $statistics->where('stat', '=', 'difficulty')->first()?->value ?? 0;
        $lastBlock = Blocks::orderBy('id', 'desc')->first();


        // STEP 2: the coins mined, cut to thousands or millions,
        // and their share of the 18.3 million
        // ======================================================
        if ($money >= 1000000) {
            $moneyResult = floor($money / 1000000) . ' mln.';
        } elseif ($money >= 1000) {
            $moneyResult = floor($money / 1000) . ' tūkst.';
        } else {
            $moneyResult = $money;
        }

        $moneyPercent = round(($money / 18300000) * 100, 1);


        // STEP 3: the hash rate, in H/s or cut to kH/s or MH/s
        // ====================================================
        if ($hashRate >= 1000000) {
            $hashResult = floor($hashRate / 1000000) . ' MH/s';
        } elseif ($hashRate >= 1000) {
            $hashResult = floor($hashRate / 1000) . ' kH/s';
        } else {
            $hashResult = $hashRate . ' H/s';
        }


        // STEP 4: the page, with the reward converted from
        // atomic units to LIT
        // ================================================
        return view(
            'welcome',
            [
                'money'           => $moneyResult,
                'moneyPercent'    => $moneyPercent,
                'lastBlockAmount' => ($lastBlock?->amount ?? 0) / 1000000000000,
                'lastBlockHeight' => $lastBlock?->block_height ?? 0,
                'hashRate'        => $hashResult,
                'difficulty'      => $difficulty,
            ]
        );
    }
}
