<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StudlyCaseTableUnseeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Skip if table does not exists.
        if (!Schema::hasTable('snake_case')) {
            return;
        }

        DB::table('snake_case')
            ->whereIn('name', ['foo', 'bar', 'baz'])
            ->delete();
    }
}
