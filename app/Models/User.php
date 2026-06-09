<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Osiset\ShopifyApp\Contracts\ShopModel as IShopModel;
use Osiset\ShopifyApp\Traits\ShopModel;

class User extends Authenticatable implements IShopModel
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, ShopModel;

    /**
     * Get the bulk edit tasks for this shop.
     */
    public function bulkEditTasks(): HasMany
    {
        return $this->hasMany(BulkEditTask::class);
    }

    /**
     * Check if this shop is on the free plan (no paid plan, not freemium, not grandfathered).
     */
    public function isFree(): bool
    {
        return !$this->plan && !$this->isFreemium() && !$this->isGrandfathered();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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
           // 'password' => 'hashed',
        ];
    }
}
