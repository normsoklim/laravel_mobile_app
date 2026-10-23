<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'full_name',
        'nick_name',
        'email',
        'phone',
        'password',
        'dob',
        'gender',
        'avatar',
        'google_id',
        'facebook_id',
        'apple_id',
        'is_active',
        'role',
        'email_verified_at',
        'email_verification_code',
        'email_verification_expires_at',
        'pin_hash',
        'pin_created_at',
        'preferred_language',
    ];

    // Appended attributes included in JSON responses (login, profile, etc.)
    protected $appends = [
        'has_google_account',
        'has_facebook_account',
        'has_pin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_code',
        'pin_hash',
        'password_reset_otp',
        'password_reset_otp_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_expires_at' => 'datetime',
            'password_reset_otp_expires_at' => 'datetime',
            'pin_created_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function socialAccounts()
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function cart()
    {
        return $this->hasOne(Cart::class);
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Deliveries assigned to this user (when they are a delivery person).
     */
    public function deliveries()
    {
        return $this->hasMany(Delivery::class, 'delivery_user_id');
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    // Accessors for the appended attributes consumed by the Flutter client
    public function getHasGoogleAccountAttribute(): bool
    {
        return $this->socialAccounts()->where('provider', 'google')->exists();
    }

    public function getHasFacebookAccountAttribute(): bool
    {
        return $this->socialAccounts()->where('provider', 'facebook')->exists();
    }

    public function getHasPinAttribute(): bool
    {
        return !empty($this->pin_hash);
    }

    public function hasGoogleAccount(): bool
    {
        return $this->socialAccounts()->where('provider', 'google')->exists();
    }

    public function hasFacebookAccount(): bool
    {
        return $this->socialAccounts()->where('provider', 'facebook')->exists();
    }

    public function getGoogleAccount(): ?SocialAccount
    {
        return $this->socialAccounts()->where('provider', 'google')->first();
    }

    public function getFacebookAccount(): ?SocialAccount
    {
        return $this->socialAccounts()->where('provider', 'facebook')->first();
    }
}
