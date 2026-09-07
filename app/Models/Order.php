<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id',
        'address_id',
        'courier_id',
        'status',
        'shopping_list',
        'items_subtotal',
        'commission',
        'total',
        'payment_method',
        'payment_status',
        'notes',
        'created_by',
        'confirmed_at',
        'purchased_at',
        'delivered_at',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'items_subtotal' => 'decimal:2',
            'commission' => 'decimal:2',
            'total' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'purchased_at' => 'datetime',
            'delivered_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Address, $this>
     */
    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    /**
     * The courier (user) assigned to fulfill the order.
     *
     * @return BelongsTo<User, $this>
     */
    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    /**
     * The user (admin) who created the order.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Recalculate the money totals from the priced order items.
     */
    public function recalculateTotals(): void
    {
        $subtotal = (float) $this->items()->whereNotNull('line_total')->sum('line_total');

        $this->items_subtotal = $subtotal > 0 ? $subtotal : null;
        $this->total = $this->items_subtotal !== null
            ? round($subtotal + (float) ($this->commission ?? 0), 2)
            : null;

        $this->save();
    }
}
