<?php

// -----------------------------------------------------------
//  [*] Kernel — the console kernel
//
//  What php artisan can run: the framework's commands plus
//  the closure commands of routes/console.php, bitlitas:sync
//  among them. Nothing is scheduled — the container runs no
//  cron, and nothing calls schedule:run.
//
//  Used by:
//    - bootstrap/app.php — bound as the console kernel
//    - artisan — handles every command through it
// -----------------------------------------------------------


namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;








// -----------------------------------------------------------
// Kernel
// -----------------------------------------------------------
//
//   schedule — the scheduled commands (none)
//   commands — where the app's commands come from
//
// Used by:
//   - bootstrap/app.php, artisan
// -----------------------------------------------------------

class Kernel extends ConsoleKernel
{

    // Command classes registered by name: none, the app's
    // commands are closures
    protected $commands = [];






    // -----------------------------------------------------------
    // schedule
    // -----------------------------------------------------------
    //
    // The commands schedule:run would start: none.
    //
    // Used by:
    //   - schedule:run — which nothing runs at the moment
    // -----------------------------------------------------------

    protected function schedule(Schedule $schedule)
    {
    }






    // -----------------------------------------------------------
    // commands
    // -----------------------------------------------------------
    //
    // Loads the command classes of app/Console/Commands — a
    // folder the app does not have, which Laravel skips — and
    // the closure commands of routes/console.php.
    //
    // Used by:
    //   - Laravel's console kernel — when it boots
    // -----------------------------------------------------------

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
