<?php

// -----------------------------------------------------------
//  [*] CreateBlocksTable — the blocks table
//
//  One row per block the sync stores: its height, its time
//  and its reward in atomic units, both bigint, which holds
//  any reward and any sum of them. Nothing in the table is
//  nullable but Laravel's timestamps, and nothing is keyed
//  but the id — strict mode refuses a row without its time,
//  which is why seedDB.sh's first stand-in block never lands.
//
//  Used by:
//    - php artisan migrate — seedDB.sh in production,
//      tests/container/run.sh in the test container
// -----------------------------------------------------------


use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;








// -----------------------------------------------------------
// CreateBlocksTable
// -----------------------------------------------------------
//
//   up   — creates the table
//   down — drops it
//
// Used by:
//   - php artisan migrate and migrate:rollback
// -----------------------------------------------------------

class CreateBlocksTable extends Migration
{






    // -----------------------------------------------------------
    // up
    // -----------------------------------------------------------
    //
    // The table with an auto-increment id, the block's height
    // (int), time and reward (bigint), and created_at and
    // updated_at.
    //
    // Used by:
    //   - php artisan migrate
    // -----------------------------------------------------------

    public function up()
    {
        Schema::create('blocks', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('block_height');
            $table->bigInteger('timestamp');
            $table->bigInteger('amount');
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
        Schema::dropIfExists('blocks');
    }
}
