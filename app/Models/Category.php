<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Product;
use App\Models\CategoryTranslation;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'icon',
        'icon_url',
    ];

    protected $appends = [
        'translated_name',
    ];

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id', 'id');
    }

    /**
     * All translations for this category (one per locale).
     */
    public function translations(): HasMany
    {
        return $this->hasMany(CategoryTranslation::class, 'category_id', 'id');
    }

    /**
     * Get the translation for a specific locale, falling back to English.
     */
    public function getTranslation(string $locale = 'en'): ?CategoryTranslation
    {
        if (!$this->relationLoaded('translations')) {
            $this->load('translations');
        }

        $translation = $this->translations->firstWhere('locale', $locale);

        // Fall back to English if the requested locale is missing.
        if (!$translation && $locale !== 'en') {
            $translation = $this->translations->firstWhere('locale', 'en');
        }

        return $translation;
    }

    /**
     * Returns the category name in the current request locale,
     * falling back to the English name (categories table `name` column)
     * if no translation exists.
     */
    public function getTranslatedNameAttribute(): string
    {
        $locale = app()->getLocale();

        $translation = $this->getTranslation($locale);

        if ($translation && $translation->name) {
            return $translation->name;
        }

        return $this->name ?? '';
    }
}