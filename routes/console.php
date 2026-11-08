<?php

// -----------------------------------------------------------
//  [*] console — the app's artisan commands
//
//  The closure commands artisan offers besides the
//  framework's own:
//
//    inspire              — prints an inspiring quote (scaffold)
//    bitlitas:sync        — syncs with the full node to its top
//                           block
//    bitlitas:prune-cache — removes the file cache's expired
//                           entries
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
// block, in syncs of 10,000 blocks — rows whose fees it fills
// in and new blocks together: one line per sync saying where
// it got to, and how many rows got their fees, then the
// figures. Exits 0 at the top, and 1 with the reason when the
// node failed, when the node is behind the table or when
// another sync holds the lock. docker exec runs it as the
// container's user, 1000, the one Apache serves the site as,
// so any file it creates stays writable for the site
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

        if ($result['status'] === 'behind') {
            $this->error("The node is behind the table: its top block is {$result['node_top_block']}, the table holds blocks up to {$result['last_stored_block']}");

            return 1;
        }

        if ($result['filled'] > 0) {
            $this->line("Filled in the fees of {$result['filled']} stored blocks");
        }

        $this->line("Stored up to block {$result['last_stored_block']} of {$result['node_top_block']}");
    } while ($result['remaining'] > 0 && $result['fetched'] + $result['filled'] > 0);

    $this->info("Mined: {$result['mined']} LIT, hash rate: {$result['hash_rate']} H/s, difficulty: {$result['difficulty']}");

    return 0;
})->describe('Copy new blocks and network statistics from the full node');

// Removes the entries of the file cache whose time has run
// out — the /blokai throttle keeps two for every visitor's
// address — and prints how many. Laravel's file store
// deletes an expired entry only when its key is read again,
// so the entries of an address that never comes back would
// stay on the RAM-backed tmpfs until the container restarts.
// An entry kept forever is left alone, and so are the
// folders, which a visit may be writing into. bitlitas-cron
// runs it every five minutes
Artisan::command('bitlitas:prune-cache', function () {
    $removed = 0;
    $root = config('cache.stores.file.path');

    if (is_dir($root)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $entry) {
            // An entry starts with the ten digits of its expiry time; 9999999999 is forever
            $expires = (int) @file_get_contents($entry->getPathname(), false, null, 0, 10);

            if ($expires < time() && @unlink($entry->getPathname())) {
                $removed++;
            }
        }
    }

    $this->line("Removed $removed expired cache entries");

    return 0;
})->describe('Remove the expired entries of the file cache');
