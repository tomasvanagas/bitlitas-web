<?php

use App\Services\DaemonException;
use App\Services\NetworkSync;
use Illuminate\Foundation\Inspiring;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->describe('Display an inspiring quote');

/*
 * Bring the blocks and statistics up to date with the full node
 * (BITLITAS_DAEMON_URL), all the way to its top block. Run it as the
 * web server's user, so any file it creates stays writable for the site:
 *
 *   docker-compose exec -u www-data bitlitas-web php artisan bitlitas:sync
 */
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
