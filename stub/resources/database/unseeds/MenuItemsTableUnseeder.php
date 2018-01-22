<?php

use Illuminate\Database\Seeder;
use TCG\Voyager\Models\Menu;
use TCG\Voyager\Models\MenuItem;

class MenuItemsTableUnseeder extends Seeder
{
    /**
     * Remove menu data.
     *
     * @return void
     */
    public function run()
    {
        $url = '/admin/snake_case';

        $menu = Menu::where('name', 'admin')->firstOrFail();

        MenuItem::where('menu_id', $menu->id)
                ->where('url', $url)
                ->delete();
    }
}
