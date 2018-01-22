<?php

use Illuminate\Database\Seeder;
use TCG\Voyager\Models\Permission;
use TCG\Voyager\Models\Role;

class PermissionsTableSeeder extends Seeder
{
    /**
     * Auto generated seed file.
     *
     * @return void
     */
    public function run()
    {
        // Skip if already exists
        if (Permission::where('table_name', 'snake_case')->first()) {
            return;
        }

        Permission::generateFor('snake_case');

        $role = Role::where('name', 'admin')->first();

        if (!is_null($role)) {
            $role->permissions()->attach(
                Permission::where('table_name', 'snake_case')->pluck('id')->all()
            );
        }
    }
}
