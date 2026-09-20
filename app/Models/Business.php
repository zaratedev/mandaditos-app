<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A tenant: the mandaditos business whose subdomain/slug fronts the public order
 * portal and scopes its staff, clients and orders.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $status
 */
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'status',
    ];

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'tenant_id');
    }
}
