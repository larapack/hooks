<?php

use Illuminate\Database\Seeder;

class DatabaseUnseeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->seed('StudlyCaseTableSeeder');
        $this->seed('MenuItemsTableUnseeder');
        $this->seed('PermissionsTableUnseeder');
    }
}
