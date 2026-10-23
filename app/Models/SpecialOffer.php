<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SpecialOffer extends Model
{
    protected $fillable = [
        'title',
        'description',
        'discount_percentage',
        'image',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            // Float so JSON emits a number (Flutter expects double).
            'discount_percentage' => 'float',

            // Timestamps so the client receives ISO-8601 strings.
            'start_date' => 'datetime',
            'end_date' => 'datetime',

            'is_active' => 'boolean',
        ];
    }

    /**
     * Only offers that are switched on and currently within their
     * validity window. NULL bounds are treated as unbounded, so an
     * offer with no dates is always considered valid.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $q) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            });
    }
}
