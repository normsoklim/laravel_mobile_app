<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    
    private const COUNTRIES = [
        ['United States', '🇺🇸', 'US', '+1'],
        ['United Kingdom', '🇬🇧', 'GB', '+44'],
        ['Canada', '🇨🇦', 'CA', '+1'],
        ['Australia', '🇦🇺', 'AU', '+61'],
        ['Germany', '🇩🇪', 'DE', '+49'],
        ['France', '🇫🇷', 'FR', '+33'],
        ['Spain', '🇪🇸', 'ES', '+34'],
        ['Italy', '🇮🇹', 'IT', '+39'],
        ['India', '🇮🇳', 'IN', '+91'],
        ['China', '🇨🇳', 'CN', '+86'],
        ['Japan', '🇯🇵', 'JP', '+81'],
        ['South Korea', '🇰🇷', 'KR', '+82'],
        ['Singapore', '🇸🇬', 'SG', '+65'],
        ['Vietnam', '🇻🇳', 'VN', '+84'],
        ['Cambodia', '🇰🇭', 'KH', '+855'],
        ['Indonesia', '🇮🇩', 'ID', '+62'],
        ['Malaysia', '🇲🇾', 'MY', '+60'],
        ['Philippines', '🇵🇭', 'PH', '+63'],
        ['Brazil', '🇧🇷', 'BR', '+55'],
        ['Mexico', '🇲🇽', 'MX', '+52'],
        ['Russia', '🇷🇺', 'RU', '+7'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::COUNTRIES as [$name, $flag, $isoCode, $dialCode]) {
            Country::updateOrCreate(
                ['name' => $name],
                [
                    'flag' => $flag,
                    'iso_code' => $isoCode,
                    'dial_code' => $dialCode,
                    'is_active' => true,
                ],
            );
        }
    }
}
