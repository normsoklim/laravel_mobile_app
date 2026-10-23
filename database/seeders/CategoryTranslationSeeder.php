<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryTranslation;
use Illuminate\Database\Seeder;

class CategoryTranslationSeeder extends Seeder
{
    /**
     * Seed Khmer translations for existing categories.
     *
     * Uses updateOrCreate so the seeder can be re-run safely.
     * Matches categories by their English `name` (stored on the categories
     * table) and adds a `km` locale translation.
     */
    public function run(): void
    {
        $translations = [
            // English name => Khmer name
            'Clothes' => 'សម្លៀកបំពាក់',
            'Shoes' => 'ស្បែកជើង',
            'Bags' => 'កាតាប',
            'Electronics' => 'ឧបករណ៍អេឡិចត្រូនិក',
            'Watch' => 'នាឡិកា',
            'Jewelry' => 'គ្រឿងអលង្ការ',
            'Kitchen' => 'គ្រឿងផ្ទះបាយ',
            'Toys' => 'ប្រដាប់ក្មេងលេង',
        ];

        foreach ($translations as $englishName => $khmerName) {
            $category = Category::where('name', $englishName)->first();

            if (!$category) {
                continue;
            }

            CategoryTranslation::updateOrCreate(
                [
                    'category_id' => $category->id,
                    'locale' => 'km',
                ],
                [
                    'name' => $khmerName,
                ]
            );
        }
    }
}
