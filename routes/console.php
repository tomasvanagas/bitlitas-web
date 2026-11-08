<?php

// -----------------------------------------------------------
//  [*] console — the app's artisan commands
//
//  The closure commands artisan offers besides the
//  framework's own:
//
//    inspire       — prints an inspiring quote (scaffold)
//    bitlitas:sync — syncs with the full node to its top block
//
//  Used by:
//    - the console Kernel's commands (app/Console) — loads it
//      when artisan starts
// -----------------------------------------------------------


use App\Services\DaemonException;
use App\Services\NetworkSync;
use Illuminate\Foundation\Inspiring;

// The scaffold's sample command
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->describe('Display an inspiring quote');

// Brings the blocks and statistics up to date with the full
// node named by BITLITAS_DAEMON_URL, all the way to its top
// block, in syncs of 10,000 blocks: one line per sync saying
// where it got to, then the figures. Exits 0 at the top, and
// 1 with the reason when the node failed or another sync
// holds the lock. docker exec runs it as the container's user,
// 1000, the one php-fpm serves as, so any file it creates
// stays writable for the site
Artisan::command('bitlitas:sync', function (NetworkSync $sync) {
    do {
        try {
            $result = $sync->run(10000);
        } catch (DaemonException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        if ($result['status'] === 'busy') {
            $this->error('Another sync is running');

            return 1;
        }

        $this->line("Stored up to block {$result['last_stored_block']} of {$result['node_top_block']}");
    } while ($result['remaining'] > 0 && $result['fetched'] > 0);

    $this->info("Mined: {$result['mined']} LIT, hash rate: {$result['hash_rate']} H/s, difficulty: {$result['difficulty']}");

    return 0;
})->describe('Copy new blocks and network statistics from the full node');
