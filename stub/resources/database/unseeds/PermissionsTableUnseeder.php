<?php

use Illuminate\Database\Seeder;
use TCG\Voyager\Models\Permission;

class PermissionsTableUnseeder extends Seeder
{
    /**
     * Remove permissions data file.
     *
     * @return void
     */
    public function run()
    {
        Permission::removeFrom('snake_case');
    }
}
