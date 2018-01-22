<?php

use Illuminate\Database\Seeder;
use TCG\Voyager\Models\Menu;
use TCG\Voyager\Models\MenuItem;

class MenuItemsTableSeeder extends Seeder
{
    /**
     * Auto generated seed file.
     *
     * @return void
     */
    public function run()
    {
        $menu = Menu::where('name', 'admin')->firstOrFail();

        $url = '/admin/snake_case';

        // Skip if already exists
        if (MenuItem::where('menu_id', $menu->id)->where('url', $url)->first()) {
            return;
        }

        // Return next order index
        $lastItem = MenuItem::select('order')
                            ::where('menu_id', $menu->id)
                            ->where('url', $url)
                            ->orderBy('order', 'desc')
                            ->first();

        // Add menu item
        MenuItem::create([
            'menu_id'    => $menu->id,
            'url'        => $url,
            'title'      => 'snake_case',
            'target'     => '_self',
            'icon_class' => 'voyager-megaphone',
            'color'      => null,
            'parent_id'  => null,
            'order'      => $lastItem->order,
        ]);
    }
}
