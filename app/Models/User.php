<?php

namespace App\Models;

use App\Enums\AccountType;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'email_verified_at',
        'phone',
        'password',
        'account_type',
        'has_account',
        'is_active',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'has_account' => 'boolean',
            'account_type' => AccountType::class,
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function scopeCustomers(Builder $query): Builder
    {
        return $query->where('account_type', AccountType::Customer->value);
    }

    public function scopeAdmins(Builder $query): Builder
    {
        return $query->where('account_type', AccountType::Admin->value);
    }

    public function scopeRegisteredCustomers(Builder $query): Builder
    {
        return $query->customers()->where('has_account', true);
    }

    public function scopeManualCustomers(Builder $query): Builder
    {
        return $query->customers()->where('has_account', false);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeEligibleForPasswordReset(Builder $query): Builder
    {
        return $query->where('has_account', true)
            ->whereNotNull('email')
            ->where('is_active', true);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'created_by');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    public function wishlist(): HasOne
    {
        return $this->hasOne(Wishlist::class);
    }

    public function customerAddresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function defaultAddress(): HasOne
    {
        return $this->hasOne(CustomerAddress::class)
            ->where('is_default', true);
    }
}
