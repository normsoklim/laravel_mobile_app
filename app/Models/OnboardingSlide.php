<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A single slide of the onboarding / walkthrough carousel.
 *
 * The illustration lives in Cloudinary — this table only stores the URL, the
 * optional captions (English / Khmer) and the display order.
 */
class OnboardingSlide extends Model
{
    protected $fillable = [
        'key',
        'image_url',
        'title',
        'title_km',
        'description',
        'description_km',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Only the slides that are visible in the app.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The caption for the given locale, falling back to the English one.
     */
    public function titleFor(?string $locale = null): ?string
    {
        $locale = $locale ?: app()->getLocale();

        if ($locale !== 'en' && ! empty($this->title_km)) {
            return $this->title_km;
        }

        return $this->title;
    }

    /**
     * The description for the given locale, falling back to the English one.
     */
    public function descriptionFor(?string $locale = null): ?string
    {
        $locale = $locale ?: app()->getLocale();

        if ($locale !== 'en' && ! empty($this->description_km)) {
            return $this->description_km;
        }

        return $this->description;
    }
}
