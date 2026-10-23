<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductTranslation extends Model
{
    protected $fillable = [
        'product_id',
        'locale',
        'name',
        'description',
    ];

    public $timestamps = true;

    /**
     * Get the product that owns this translation.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    /**
     * Scope a query to only include a specific locale.
     */
    public function scopeLocale($query, string $locale)
    {
        return $query->where('locale', $locale);
    }
}
