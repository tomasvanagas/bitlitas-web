<?php

// -----------------------------------------------------------
//  [*] AddFeesToBlocksTable — the fees of each block
//
//  A block's reward, in amount, is what its miner took: the
//  coins the block made and the fees its transactions paid.
//  fees holds that second part, in atomic units, so the
//  mined figure can count the coins made alone. It is null
//  for a row stored before the column was there; the sync
//  fills those in, oldest first, from the node.
//
//  Used by:
//    - php artisan migrate — runUpdateThisStack.sh and
//      seedDB.sh in production, tests/container/run.sh in
//      the test container
// -----------------------------------------------------------


use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;








// -----------------------------------------------------------
// AddFeesToBlocksTable
// -----------------------------------------------------------
//
//   up   — adds the column
//   down — drops it
//
// Used by:
//   - php artisan migrate and migrate:rollback
// -----------------------------------------------------------

class AddFeesToBlocksTable extends Migration
{






    // -----------------------------------------------------------
    // up
    // -----------------------------------------------------------
    //
    // fees (bigint, nullable) after amount.
    //
    // Used by:
    //   - php artisan migrate
    // -----------------------------------------------------------

    public function up()
    {
        Schema::table('blocks', function (Blueprint $table) {
            $table->bigInteger('fees')->nullable()->after('amount');
        });
    }






    // -----------------------------------------------------------
    // down
    // -----------------------------------------------------------
    //
    // Drops the column.
    //
    // Used by:
    //   - php artisan migrate:rollback
    // -----------------------------------------------------------

    public function down()
    {
        Schema::table('blocks', function (Blueprint $table) {
            $table->dropColumn('fees');
        });
    }
}
