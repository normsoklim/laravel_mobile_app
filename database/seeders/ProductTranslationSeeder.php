<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductTranslation;
use Illuminate\Database\Seeder;

class ProductTranslationSeeder extends Seeder
{
    /**
     * Seed Khmer translations for existing products.
     *
     * Uses updateOrCreate so the seeder can be re-run safely.
     * Matches products by their English `name` (stored on the products
     * table) and adds a `km` locale translation.
     */
    public function run(): void
    {
        $translations = [
            // English name => [Khmer name, Khmer description]
            'Short-Sleeve Striped Denim Shirt' => [
                'name' => 'អាវយឺតដៃខ្លីឆ្នូត',
                'description' => 'អាវយឺតដៃខ្លីឆ្នូតស្រុកខ្ពស់ អាចសប្បាយកម្ម អាចអាចប្រើប្រាស់ប្រាប់ថ្មីៗ។',
            ],
            'Classic Cotton T-Shirt' => [
                'name' => 'អាវត្រូវការផ្សេងៗដែលបានបង្កើតពីផុសកុង',
                'description' => 'អាវត្រូវការផ្សេងៗដែលបានបង្កើតពីផុសកុងមានគុណភាពខ្ពស់ អាចសប្បាយកម្ម។',
            ],
            'Leather Casual Shoes' => [
                'name' => 'ស្បែកជើងសម្លៀកបំពាក់ដែលបង្កើតពីឆ្ងាយ',
                'description' => 'ស្បែកជើងសម្លៀកបំពាក់ដែលបង្កើតពីឆ្ងាយមានគុណភាពខ្ពស់ អាចប្រើប្រាស់ប្រាប់ថ្មីៗ។',
            ],
            'Wireless Bluetooth Headphones' => [
                'name' => 'អាវស្រឡាញ់សំខាន់ប្ដូរប្ដូរមិនមានសាយដ្ឋាន',
                'description' => 'អាវស្រឡាញ់សំខាន់ប្ដូរប្ដូរមិនមានសាយដ្ឋានមានសម្រាប់ការអារម្មណ៍ទំនងនឹងចម្លងរបស់អ្នក។',
            ],
            'Stainless Steel Water Bottle' => [
                'name' => 'កញ្ចក់ទឹកដែលបង្កើតពីស្ទេលែនស្ថិល',
                'description' => 'កញ្ចក់ទឹកដែលបង្កើតពីស្ទេលែនស្ថិលមានគុណភាពខ្ពស់ អាចរក្សាទឹកដែលរហ័សនៅក្នុងកញ្ចក់បាន។',
            ],
            // Products currently in the database
            "Men's Varsity Jacket" => [
                'name' => 'អាវវារ៉ាស្ដីជាក់អាវ',
                'description' => 'អាវវារ៉ាស្ដីជាក់អាវមានគុណភាពខ្ពស់ អាចប្រើប្រាស់ប្រាប់ថ្មីៗ។',
            ],
            'Classic Backpack' => [
                'name' => 'កាប់ស្បែកកែលសីកុង',
                'description' => 'កាប់ស្បែកកែលសីកុងមានគុណភាពខ្ពស់ អាចប្រើប្រាស់ប្រាប់ថ្មីៗ។',
            ],
            "Bruno Marc Men's Dress Shoes" => [
                'name' => 'ស្បែកជើងប្រើសម្រាប់ប្រាស់ប្រាប់បុរស',
                'description' => 'ស្បែកជើងប្រើសម្រាប់ប្រាស់ប្រាប់បុរសមានគុណភាពខ្ពស់ អាចប្រើប្រាស់ប្រាប់ថ្មីៗ។',
            ],
            'Samba Shoes' => [
                'name' => 'ស្បែកសំបាម៉ា',
                'description' => 'ស្បែកសំបាម៉ាមានគុណភាពខ្ពស់ អាចប្រើប្រាស់ប្រាប់ថ្មីៗ។',
            ],
            'Mens Premium Regular' => [
                'name' => 'អាវបុរសស្រុកខ្ពស់បច្ចុប្បន្ន',
                'description' => 'អាវបុរសស្រុកខ្ពស់បច្ចុប្បន្នមានគុណភាពខ្ពស់ អាចសប្បាយកម្ម។',
            ],
            'Short Sleeve Denim Shirt' => [
                'name' => 'អាវឆ្នូតជើងទឹកស្រុក',
                'description' => 'អាវឆ្នូតជើងទឹកស្រុកមានគុណភាពខ្ពស់ អាចប្រើប្រាស់ប្រាប់ថ្មីៗ។',
            ],
            'Textured Short-Sleeve Shirt' => [
                'name' => 'អាវយឺតដៃជើងទឹកមានរចនាបទ',
                'description' => 'អាវយឺតដៃជើងទឹកមានរចនាបទមានគុណភាពខ្ពស់ អាចប្រើប្រាស់ប្រាប់ថ្មីៗ។',
            ],
            'Short-Sleeve Tipped Raglan Polo Shirt' => [
                'name' => 'អាវបូឡូរាគ្លានជើងទឹកមានក្រឡាញ់ក្រឡាញ់',
                'description' => 'អាវបូឡូរាគ្លានជើងទឹកមានក្រឡាញ់ក្រឡាញ់មានគុណភាពខ្ពស់ អាចប្រើប្រាស់ប្រាប់ថ្មីៗ។',
            ],
            'Cap With Printed' => [
                'name' => 'មុខងុយមានរូបថ្នាក់',
                'description' => 'មុខងុយមានរូបថ្នាក់មានគុណភាពខ្ពស់ អាចប្រើប្រាស់ប្រាប់ថ្មីៗ។',
            ],
            'Elara Handle Bag' => [
                'name' => 'កាប់ស្បែកអេឡារ៉ា',
                'description' => 'កាប់ស្បែកអេឡារ៉ាមានគុណភាពខ្ពស់ អាចប្រើប្រាស់ប្រាប់ថ្មីៗ។',
            ],
            'Golden Hour Watch' => [
                'name' => 'ម៉ាស៊ីននាឡិករពន្លាមាសរាយ',
                'description' => 'ម៉ាស៊ីននាឡិករពន្លាមាសរាយមានគុណភាពខ្ពស់ អាចប្រើប្រាស់ប្រាប់ថ្មីៗ។',
            ],
        ];

        foreach ($translations as $englishName => $khmer) {
            $product = Product::where('name', $englishName)->first();

            if (!$product) {
                continue;
            }

            ProductTranslation::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'locale' => 'km',
                ],
                [
                    'name' => $khmer['name'],
                    'description' => $khmer['description'],
                ]
            );
        }
    }
}
