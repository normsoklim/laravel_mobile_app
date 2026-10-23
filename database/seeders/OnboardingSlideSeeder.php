<?php

namespace Database\Seeders;

use App\Models\OnboardingSlide;
use Illuminate\Database\Seeder;

class OnboardingSlideSeeder extends Seeder
{
    public function run(): void
    {
        $slides = [
            [
                'key' => 'welcome',
                'image_url' => 'https://res.cloudinary.com/dyojlvfew/image/upload/v1792589211/H62f4dd2d390b42879899e70c36652dbdQ-removebg-preview.png',
                'title' => 'Welcome to Tinh Shop',
                'title_km' => 'សូមស្វាគមន៍មកកាន់ Tinh Shop',
                'description' => 'Discover the best products with fast delivery.',
                'description_km' => 'ស្វែងរកផលិតផលដ៏ល្អជាមួយការប្រគល់ដល់លឿជាង។',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'key' => 'shop',
                'image_url' => 'https://res.cloudinary.com/dyojlvfew/image/upload/v1792589211/H62f4dd2d390b42879899e70c36652dbdQ-removebg-preview.png',
                'title' => 'Shop with confidence',
                'title_km' => 'ទិញដល់មានភាពជាភាពផ្តល់ជូន',
                'description' => 'Pay securely with KHQR or the Tinh Shop wallet.',
                'description_km' => 'បង់ប្រាក់ដោយមានសុវត្ថិភាព ជាមួយ KHQR ឬ កាបូប Tinh Shop។',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'key' => 'delivery',
                'image_url' => 'https://res.cloudinary.com/dyojlvfew/image/upload/v1792589331/f2ffec7800fce64e912e0249b244c94f-removebg-preview.png',
                'title' => 'Track your delivery',
                'title_km' => 'តាមដានការប្រគល់របស់អ្នក',
                'description' => 'Follow your order live on the map.',
                'description_km' => 'តាមដានការបញ្ជាទិញរបស់អ្នកជាផ្ទាល់នៅលើផែនទីស្វែងរក។',
                'sort_order' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($slides as $slide) {
            OnboardingSlide::updateOrCreate(
                ['key' => $slide['key']],
                $slide,
            );
        }
    }
}
