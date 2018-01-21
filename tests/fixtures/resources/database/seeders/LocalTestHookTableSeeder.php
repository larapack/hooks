<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocalTestHookTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Don't add data if the data is already there
        if (DB::table('local_test_hook')->count() > 0) {
            return;
        }

        DB::table('local_test_hook')->insert([
            ['name' => 'foo'],
            ['name' => 'bar'],
            ['name' => 'baz'],
        ]);
    }
}
