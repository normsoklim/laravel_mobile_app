<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Category;
use App\Models\CartItem;
use App\Models\ProductTranslation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Product extends Model
{

    protected $appends = [
        'sold',
        'is_favorite',
        'review_count',
        'rating',
        'translated_name',
        'translated_description',
    ];

    protected $fillable = [
        'name',
        'price',
        'image',
        'images',
        'description',
        'sold_count',
        'sizes',
        'colors',
        'category_id',
        'stock',
    ];

    protected function casts(): array
    {
        return [
            // Floats so JSON emits numbers (Flutter expects double).
            'price' => 'float',

            // JSON columns decoded to arrays (Flutter expects List<String>).
            'images' => 'array',
            'sizes' => 'array',
            'colors' => 'array',

            'sold_count' => 'integer',
            'stock' => 'integer',
        ];
    }
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    /**
     * All translations for this product (one per locale).
     */
    public function translations(): HasMany
    {
        return $this->hasMany(ProductTranslation::class, 'product_id', 'id');
    }

    /**
     * Get the translation for a specific locale, falling back to English.
     */
    public function getTranslation(string $locale = 'en'): ?ProductTranslation
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

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Number of reviews, derived from the reviews table
     * (the count is no longer stored on the products table).
     */
    public function getReviewCountAttribute(): int
    {
        // withCount('reviews') eager-loads the count as
        // `reviews_count`, avoiding per-product queries.
        if (array_key_exists('reviews_count', $this->attributes)) {
            return (int) $this->attributes['reviews_count'];
        }

        if ($this->relationLoaded('reviews')) {
            return $this->reviews->count();
        }

        return $this->reviews()->count();
    }

    /**
     * Average rating (0.0 - 5.0), derived from the reviews
     * table (the rating is no longer stored on the products table).
     */
    public function getRatingAttribute(): float
    {
        // withAvg('reviews', 'rating') eager-loads the average
        // as `reviews_avg_rating`, avoiding per-product queries.
        if (array_key_exists('reviews_avg_rating', $this->attributes)) {
            return round((float) $this->attributes['reviews_avg_rating'], 1);
        }

        if ($this->relationLoaded('reviews')) {
            $average = $this->reviews->avg('rating');

            return $average === null ? 0.0 : round((float) $average, 1);
        }

        return round((float) $this->reviews()->avg('rating'), 1);
    }

    public function getSoldAttribute(): string
    {
        $count = (int) ($this->sold_count ?? 0);

        if ($count >= 1000000) {
            return $this->abbreviateNumber($count / 1000000).'M';
        }

        if ($count >= 1000) {
            return $this->abbreviateNumber($count / 1000).'k';
        }

        return (string) $count;
    }

    private function abbreviateNumber(float $value): string
    {
        // 1.0 -> "1", 1.2 -> "1.2", 1.25 -> "1.3"
        $formatted = number_format(round($value, 1), 1, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    public function getIsFavoriteAttribute(): bool
    {
        return false;
    }

    /**
     * Returns the product name in the current request locale,
     * falling back to the English name (products table `name` column)
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

    /**
     * Returns the product description in the current request locale,
     * falling back to the English description (products table `description` column)
     * if no translation exists.
     */
    public function getTranslatedDescriptionAttribute(): string
    {
        $locale = app()->getLocale();

        $translation = $this->getTranslation($locale);

        if ($translation && $translation->description) {
            return $translation->description;
        }

        return $this->description ?? '';
    }
}