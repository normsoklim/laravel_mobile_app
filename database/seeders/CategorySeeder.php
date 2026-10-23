<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Categories matching the Flutter home screen menu items
     * (frontend/lib/screens/home/home_screen.dart) — labels and icon
     * asset paths are identical so the client can use them as-is.
     * Uses updateOrCreate so the seeder can be re-run safely.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Clothes', 'icon' => 'images/svg/clothes.svg'],
            ['name' => 'Shoes', 'icon' => 'images/svg/shoes.svg'],
            ['name' => 'Bags', 'icon' => 'images/svg/bags.svg'],
            ['name' => 'Electronics', 'icon' => 'images/svg/electronics.svg'],
            ['name' => 'Watch', 'icon' => 'images/svg/watch.svg'],
            ['name' => 'Jewelry', 'icon' => 'images/svg/Jewelry.svg'],
            ['name' => 'Kitchen', 'icon' => 'images/svg/Kitchen.svg'],
            ['name' => 'Toys', 'icon' => 'images/svg/Toys.svg'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name']],
                ['icon' => $category['icon']]
            );
        }
    }
}