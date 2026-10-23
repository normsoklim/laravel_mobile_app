<?php

namespace Database\Seeders;

use App\Models\SpecialOffer;
use Illuminate\Database\Seeder;

class SpecialOfferSeeder extends Seeder
{
    /**
     * Seed the special offers shown on the Special Offers screen
     * (frontend/lib/screens/product/special_offers_screen.dart). The
     * discount percentages and banner images mirror the values that
     * were previously hard-coded in the UI. Uses updateOrCreate keyed
     * on the title so the seeder can be re-run safely.
     */
    public function run(): void
    {
        $offers = [
            [
                'title' => "Today's Special!",
                'description' => 'Get discount for every order, only valid for today',
                'discount_percentage' => 30,
                'image' => 'assets/images/slide01.png',
            ],
            [
                'title' => "Today's Special!",
                'description' => 'Get discount for every order, only valid for today',
                'discount_percentage' => 25,
                'image' => 'assets/images/slide02.png',
            ],
            [
                'title' => "Today's Special!",
                'description' => 'Get discount for every order, only valid for today',
                'discount_percentage' => 40,
                'image' => 'assets/images/slide03.png',
            ],
            [
                'title' => "Today's Special!",
                'description' => 'Get discount for every order, only valid for today',
                'discount_percentage' => 20,
                'image' => 'assets/images/slide04.png',
            ],
            [
                'title' => "Today's Special!",
                'description' => 'Get discount for every order, only valid for today',
                'discount_percentage' => 20,
                'image' => 'assets/images/slide05.png',
            ],
        ];

        foreach ($offers as $offer) {
            // Key on the image so each banner stays a distinct row even
            // though several offers share the same title.
            SpecialOffer::updateOrCreate(
                ['image' => $offer['image']],
                [
                    'title' => $offer['title'],
                    'description' => $offer['description'],
                    'discount_percentage' => $offer['discount_percentage'],
                    'is_active' => true,
                ]
            );
        }
    }
}
