<?php

use Illuminate\Database\Seeder;
use TCG\Voyager\Traits\Seedable;

class DatabaseSeeder extends Seeder
{
    use Seedable;

    protected $seedersPath = __DIR__.'/';

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->seed('StudlyCaseTableSeeder');
        $this->seed('MenuItemsTableSeeder');
        $this->seed('PermissionsTableSeeder');
    }
}
