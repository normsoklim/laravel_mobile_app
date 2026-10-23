<?php

namespace Database\Seeders;

use App\Models\NavigationItem;
use Illuminate\Database\Seeder;


class NavigationItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'key' => 'home',
                'label' => 'Home',
                'label_km' => 'ទំព័រដើម',
                'icon_url' => 'https://res.cloudinary.com/dyojlvfew/image/upload/v1790951596/home.png',
                'active_icon_url' => null,
                'route_key' => 'home',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'key' => 'cart',
                'label' => 'Cart',
                'label_km' => 'កន្ត្រកទំនិញ',
                'icon_url' => 'https://res.cloudinary.com/dyojlvfew/image/upload/v1790949955/cart.png',
                'active_icon_url' => null,
                'route_key' => 'cart',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'key' => 'order',
                'label' => 'Orders',
                'label_km' => 'ការបញ្ជាទិញ',
                'icon_url' => 'https://res.cloudinary.com/dyojlvfew/image/upload/v1790951597/order.png',
                'active_icon_url' => null,
                'route_key' => 'order',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'key' => 'wallet',
                'label' => 'Wallet',
                'label_km' => 'កាបូប',
                'icon_url' => 'https://res.cloudinary.com/dyojlvfew/image/upload/v1792567750/wallet.png',
                'active_icon_url' => null,
                'route_key' => 'wallet',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'key' => 'profile',
                'label' => 'Profile',
                'label_km' => 'ប្រវត្តិរូប',
                'icon_url' => 'https://res.cloudinary.com/dyojlvfew/image/upload/v1790951597/profile.png',
                'active_icon_url' => null,
                'route_key' => 'profile',
                'sort_order' => 5,
                'is_active' => true,
            ],
            // Optional destinations — enabled by an admin when needed.
            [
                'key' => 'categories',
                'label' => 'Categories',
                'label_km' => 'ប្រភេទ',
                'icon_url' => 'images/svg/category.svg',
                'active_icon_url' => null,
                'route_key' => 'categories',
                'sort_order' => 6,
                'is_active' => false,
            ],
            [
                'key' => 'wishlist',
                'label' => 'Wishlist',
                'label_km' => 'ចំណូលចិត្ត',
                'icon_url' => 'images/svg/rating.svg',
                'active_icon_url' => null,
                'route_key' => 'wishlist',
                'sort_order' => 7,
                'is_active' => false,
            ],
        ];

        foreach ($items as $item) {
            NavigationItem::updateOrCreate(
                ['key' => $item['key']],
                $item,
            );
        }
    }
}
