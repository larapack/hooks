<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LocalTestHookTableUnseeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Skip if table does not exists.
        if (!Schema::hasTable('local_test_hook')) {
            return;
        }

        DB::table('local_test_hook')
            ->whereIn('name', ['foo', 'bar', 'baz'])
            ->delete();
    }
}
