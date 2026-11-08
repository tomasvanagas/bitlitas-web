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
    // hash rate, cut to whole kH/s; the difficulty, which the
    // view groups in thousands; the height of the block with
    // the highest id and its reward in LIT.
    //
    // Known bug (medium): the figures and the last block are
    // read without checking that they exist
    // (HomeController.php:90-93), so the page is a 500 on a
    // database seedDB.sh has not filled
    // [home-without-stats]. Fix: fall back to 0 for a missing
    // figure or block.
    //
    // Known bug (low): the mined figure has two decimals, but
    // the steps are whole numbers (HomeController.php:99-101):
    // "> 999" sends 999.5 to the thousands, where it reads
    // "0 tūkst.", and "<= 999999" sends 999,999.5 to the
    // millions, "0 mln." [mined-threshold-fractions]. Fix:
    // step at ">= 1000" and ">= 1000000".
    //
    // Known bug (low): under 1,000 the hash rate is shown with
    // no unit (HomeController.php:117) [hash-rate-no-unit],
    // and millions are written "mH/s", milli-hashes
    // (HomeController.php:115) [hash-rate-mega-unit]. Fix:
    // " H/s" for the bare number and "MH/s" for millions.
    //
    // Used by:
    //   - the visitor's browser — every visit to the site
    // -----------------------------------------------------------

    public function index()
    {
        // STEP 1: the three figures and the top stored block
        // ==================================================
        $money = Statistic::get()->where('stat', '=', 'mined')->first();
        $hashRate = Statistic::get()->where('stat', '=', 'hash_rate')->first();
        $difficulty = Statistic::get()->where('stat', '=', 'difficulty')->first();
        $lastBlock = Blocks::orderBy('id', 'desc')->first();


        // STEP 2: the coins mined, cut to thousands or millions,
        // and their share of the 18.3 million
        // ======================================================
        if ($money->value > 999 && $money->value <= 999999) {
            $moneyResult = floor($money->value / 1000) . ' tūkst.';
        } elseif ($money->value > 999999) {
            $moneyResult = floor($money->value / 1000000) . ' mln.';
        } else {
            $moneyResult = $money->value;
        }

        $moneyPercent = round(($money->value / 18300000) * 100, 1);


        // STEP 3: the hash rate, cut to kH/s or millions
        // ==============================================
        if ($hashRate->value > 999 && $hashRate->value <= 999999) {
            $hashResult = floor($hashRate->value / 1000) . ' kH/s';
        } elseif ($hashRate->value > 999999) {
            $hashResult = floor($hashRate->value / 1000000) . ' mH/s';
        } else {
            $hashResult = $hashRate->value;
        }


        // STEP 4: the page, with the reward converted from
        // atomic units to LIT
        // ================================================
        return view(
            'welcome',
            [
                'money'           => $moneyResult,
                'moneyPercent'    => $moneyPercent,
                'lastBlockAmount' => $lastBlock->amount / 1000000000000,
                'lastBlockHeight' => $lastBlock->block_height,
                'hashRate'        => $hashResult,
                'difficulty'      => $difficulty->value,
            ]
        );
    }
}
