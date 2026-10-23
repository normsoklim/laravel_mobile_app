<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A single button of the app's bottom navigation bar.
 *
 * The icon files themselves live in Cloudinary — this table only stores the
 * URLs, the caption, the Flutter route key and the display order.
 */
class NavigationItem extends Model
{
    protected $fillable = [
        'key',
        'label',
        'label_km',
        'icon_url',
        'active_icon_url',
        'route_key',
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
     * Only the buttons that are visible in the app.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The caption for the given locale, falling back to the English label.
     */
    public function labelFor(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();

        if ($locale !== 'en' && ! empty($this->label_km)) {
            return $this->label_km;
        }

        return $this->label ?? '';
    }
}
