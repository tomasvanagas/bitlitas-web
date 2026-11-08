<?php

// -----------------------------------------------------------
//  [*] CreateStatisticsTable — the statistics table
//
//  The home page's named figures: a name and a value, both
//  text, one row per figure. seedDB.sh fills in mined,
//  hash_rate and difficulty at 0; the sync rewrites them.
//
//  Used by:
//    - php artisan migrate — seedDB.sh in production,
//      tests/container/run.sh in the test container
// -----------------------------------------------------------


use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;








// -----------------------------------------------------------
// CreateStatisticsTable
// -----------------------------------------------------------
//
//   up   — creates the table
//   down — drops it
//
// Used by:
//   - php artisan migrate and migrate:rollback
// -----------------------------------------------------------

class CreateStatisticsTable extends Migration
{






    // -----------------------------------------------------------
    // up
    // -----------------------------------------------------------
    //
    // The table with an auto-increment id, the figure's name
    // and value (varchar 255 each), and created_at and
    // updated_at.
    //
    // Used by:
    //   - php artisan migrate
    // -----------------------------------------------------------

    public function up()
    {
        Schema::create('statistics', function (Blueprint $table) {
            $table->increments('id');
            $table->string('stat');
            $table->string('value');
            $table->timestamps();
        });
    }






    // -----------------------------------------------------------
    // down
    // -----------------------------------------------------------
    //
    // Drops the table, if it is there.
    //
    // Used by:
    //   - php artisan migrate:rollback
    // -----------------------------------------------------------

    public function down()
    {
        Schema::dropIfExists('statistics');
    }
}
