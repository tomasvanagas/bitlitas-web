<?php

// -----------------------------------------------------------
//  [*] SchemaTest — the database the app runs on
//
//  The bitlitas-mysql service runs mysql:5.7, MySQL 5.7.44,
//  on its stock settings: strict SQL mode, and latin1 for
//  the server and for the database its entrypoint creates.
//  seedDB.sh runs the two migrations, which build blocks —
//  a block's height, time and reward — and statistics — the
//  home page's named figures — both InnoDB in utf8mb4, as
//  Laravel creates its tables whatever the database's
//  default. Strict mode is why seedDB.sh's first stand-in
//  block, which has no time, is refused.
//
//  Used by:
//    - PHPUnit — the Platform suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Platform;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;








// -----------------------------------------------------------
// SchemaTest
// -----------------------------------------------------------
//
// Reads the server's variables and information_schema; the
// one write is rolled back.
//
//   columns — a table's columns as information_schema lists
//             them
//
// Used by:
//   - PHPUnit — the Platform suite (runTests.sh)
// -----------------------------------------------------------

class SchemaTest extends TestCase
{

    // MySQL 5.7's default SQL mode
    private const SQL_MODE = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION';






    // -----------------------------------------------------------
    // testServerIsProductionsMysql
    // -----------------------------------------------------------
    //
    // MySQL 5.7.44 in its default SQL mode, latin1 for the
    // server and for the database.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testServerIsProductionsMysql(): void
    {
        $server = DB::selectOne('SELECT VERSION() AS version, @@GLOBAL.sql_mode AS mode, @@character_set_server AS charset');
        $database = DB::selectOne('SELECT DEFAULT_CHARACTER_SET_NAME AS charset FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()');

        $this->assertSame('5.7.44', $server->version);
        $this->assertSame(self::SQL_MODE, $server->mode);
        $this->assertSame('latin1', $server->charset);
        $this->assertSame('latin1', $database->charset);
    }






    // -----------------------------------------------------------
    // testMigrationsBuiltTheTwoTables
    // -----------------------------------------------------------
    //
    // blocks and statistics, beside Laravel's migrations
    // table, which records all three migrations as run.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testMigrationsBuiltTheTwoTables(): void
    {
        $tables = array_column(DB::select('SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME'), 'name');

        $this->assertSame(['blocks', 'migrations', 'statistics'], $tables);
        $this->assertSame([
            '2018_02_03_145232_create_blocks_table',
            '2018_02_07_173350_create_statistics_table',
            '2026_11_09_000000_add_fees_to_blocks_table',
        ], DB::table('migrations')->orderBy('migration')->pluck('migration')->all());
    }






    // -----------------------------------------------------------
    // testBlocksHoldOneRowPerBlock
    // -----------------------------------------------------------
    //
    // A block's height (int), its time, its reward in atomic
    // units (bigint — a reward is ~1.8·10¹³, the total
    // ~1.8·10¹⁹ at the end of the emission), none of them
    // nullable, and the fees of its transactions, null for a
    // row stored before the column, under an auto-increment
    // id.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testBlocksHoldOneRowPerBlock(): void
    {
        $this->assertSame([
            'id'           => 'int(10) unsigned NO PRI auto_increment',
            'block_height' => 'int(11) NO',
            'timestamp'    => 'bigint(20) NO',
            'amount'       => 'bigint(20) NO',
            'fees'         => 'bigint(20) YES',
            'created_at'   => 'timestamp YES',
            'updated_at'   => 'timestamp YES',
        ], $this->columns('blocks'));
    }






    // -----------------------------------------------------------
    // testStatisticsHoldNamedFigures
    // -----------------------------------------------------------
    //
    // A figure's name and its value, both text.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testStatisticsHoldNamedFigures(): void
    {
        $this->assertSame([
            'id'         => 'int(10) unsigned NO PRI auto_increment',
            'stat'       => 'varchar(255) NO',
            'value'      => 'varchar(255) NO',
            'created_at' => 'timestamp YES',
            'updated_at' => 'timestamp YES',
        ], $this->columns('statistics'));
    }






    // -----------------------------------------------------------
    // testTablesAreInnoDbInUtf8mb4
    // -----------------------------------------------------------
    //
    // Every table is InnoDB — the transactions of the sync and
    // of the Feature suite need it — in utf8mb4_unicode_ci.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testTablesAreInnoDbInUtf8mb4(): void
    {
        $tables = DB::select('SELECT TABLE_NAME AS name, ENGINE AS engine, TABLE_COLLATION AS collation FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');

        foreach ($tables as $table) {
            $this->assertSame('InnoDB', $table->engine, $table->name);
            $this->assertSame('utf8mb4_unicode_ci', $table->collation, $table->name);
        }
    }






    // -----------------------------------------------------------
    // testBlockWithoutATimestampIsRefused
    // -----------------------------------------------------------
    //
    // seedDB.sh's first stand-in block names no timestamp,
    // which has no default; strict mode refuses the row with
    // error 1364, so only the script's second stand-in lands.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testBlockWithoutATimestampIsRefused(): void
    {
        DB::beginTransaction();
        try {
            DB::insert('INSERT INTO blocks (block_height, amount, created_at, updated_at) VALUES (0, 0, NOW(), NOW())');
            $this->fail('the row without a timestamp was stored');
        } catch (QueryException $refused) {
            $this->assertStringContainsString("1364 Field 'timestamp' doesn't have a default value", $refused->getMessage());
        } finally {
            DB::rollBack();
        }
    }






    // -----------------------------------------------------------
    // columns
    // -----------------------------------------------------------
    //
    // Each column of the table as "type nullable [key]
    // [extra]", in table order.
    //
    // Used by:
    //   - the tests above
    // -----------------------------------------------------------

    private function columns(string $table): array
    {
        $columns = [];
        $rows = DB::select('SELECT COLUMN_NAME AS name, COLUMN_TYPE AS type, IS_NULLABLE AS nullable, COLUMN_KEY AS `key`, EXTRA AS extra FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION', [$table]);
        foreach ($rows as $row) {
            $columns[$row->name] = trim(preg_replace('/\s+/', ' ', "$row->type $row->nullable $row->key $row->extra"));
        }
        return $columns;
    }
}
