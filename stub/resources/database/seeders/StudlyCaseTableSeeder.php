<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StudlyCaseTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Don't add data if the data is already there
        if (DB::table('snake_case')->count() > 0) {
            return;
        }

        DB::table('snake_case')->insert([
            ['name' => 'foo'],
            ['name' => 'bar'],
            ['name' => 'baz'],
        ]);
    }
}
