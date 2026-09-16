<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Everything the reports page says about a stretch of time, worked out once and
 * read by whoever needs it — the screen and the spreadsheet both, so a figure can
 * never say one thing in the browser and another in the downloaded file.
 *
 * Two clocks run through here on purpose. Money is counted on the day it was
 * collected, because that is the day it reached the cash box; orders are counted
 * on the day they were placed, because that is the day the work happened. Whoever
 * shows these numbers has to say which is which.
 *
 * @phpstan-type Collected array{orders: int, commission: float, moved: float, cash: float, transfer: float, ticket: float, fee: float}
 * @phpstan-type Operations array{orders: int, delivered: int, cancelled: int, perDay: float}
 * @phpstan-type CourierRow array{courier: string, orders: int, delivered: int, commission: float, moved: float, average: int|null}
 */
final class PeriodReport
{
    private const TOP_CLIENTS = 10;

    private const TOP_PRODUCTS = 15;

    private function __construct(
        private readonly CarbonImmutable $from,
        private readonly CarbonImmutable $to,
    ) {}

    /**
     * A report for the range the admin asked for: this month so far when they asked
     * for nothing, and put back in order when they hand over the dates backwards.
     */
    public static function between(?string $from, ?string $to): self
    {
        $fromDate = $from !== null && $from !== ''
            ? CarbonImmutable::parse($from)
            : CarbonImmutable::now()->startOfMonth();
        $toDate = $to !== null && $to !== ''
            ? CarbonImmutable::parse($to)
            : CarbonImmutable::now();

        if ($fromDate->greaterThan($toDate)) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        return new self($fromDate->startOfDay(), $toDate->endOfDay());
    }

    public function from(): CarbonImmutable
    {
        return $this->from;
    }

    public function to(): CarbonImmutable
    {
        return $this->to;
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    /**
     * The same stretch of time immediately before this one. A number on its own
     * says what happened; the same number against the period before it says whether
     * the business is going up or down.
     */
    public function previous(): self
    {
        return new self($this->from->subDays($this->days()), $this->from->subSecond());
    }

    /**
     * How each headline compares with that previous stretch, as a percentage.
     *
     * @return array{commission: float|null, moved: float|null, orders: float|null, delivered: float|null}
     */
    public function changes(): array
    {
        $before = $this->previous();
        $collected = $this->collected();
        $operations = $this->operations();
        $beforeCollected = $before->collected();
        $beforeOperations = $before->operations();

        return [
            'commission' => $this->change($collected['commission'], $beforeCollected['commission']),
            'moved' => $this->change($collected['moved'], $beforeCollected['moved']),
            'orders' => $this->change($operations['orders'], $beforeOperations['orders']),
            'delivered' => $this->change($operations['delivered'], $beforeOperations['delivered']),
        ];
    }

    /**
     * The money that came in during the window, keyed on the day it was collected
     * rather than the day the order was placed — an order taken last month and paid
     * this one is this month's money, and a monthly cut that says otherwise cannot
     * be reconciled against the cash box.
     *
     * The commission is the business's own income; the total is that plus whatever
     * the client's shopping cost, which only passed through the courier's hands.
     *
     * @return Collected
     */
    public function collected(): array
    {
        $row = Order::query()
            ->where('payment_status', PaymentStatus::Paid->value)
            ->whereBetween('paid_at', [$this->from, $this->to])
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(commission), 0) as commission')
            ->selectRaw('COALESCE(SUM(total), 0) as moved')
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total ELSE 0 END), 0) as cash")
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'transfer' THEN total ELSE 0 END), 0) as transfer")
            ->toBase()
            ->first();

        $orders = (int) ($row->orders_count ?? 0);
        $commission = round((float) ($row->commission ?? 0), 2);
        $moved = round((float) ($row->moved ?? 0), 2);

        return [
            'orders' => $orders,
            'commission' => $commission,
            'moved' => $moved,
            'cash' => round((float) ($row->cash ?? 0), 2),
            'transfer' => round((float) ($row->transfer ?? 0), 2),
            'ticket' => $orders > 0 ? round($moved / $orders, 2) : 0.0,
            'fee' => $orders > 0 ? round($commission / $orders, 2) : 0.0,
        ];
    }

    /**
     * What the business did in the window, counted on the day each order was placed.
     *
     * @return Operations
     */
    public function operations(): array
    {
        $row = Order::query()
            ->whereBetween('created_at', [$this->from, $this->to])
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw("SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_count")
            ->selectRaw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count")
            ->toBase()
            ->first();

        $orders = (int) ($row->orders_count ?? 0);
        $days = max(1, (int) $this->from->diffInDays($this->to) + 1);

        return [
            'orders' => $orders,
            'delivered' => (int) ($row->delivered_count ?? 0),
            'cancelled' => (int) ($row->cancelled_count ?? 0),
            'perDay' => round($orders / $days, 1),
        ];
    }

    /**
     * Money already earned and still outside the cash box: orders delivered but not
     * paid for. It deliberately ignores the date filter — a debt from March is still
     * a debt in September — and it ignores orders still in flight, because nobody
     * owes for an errand that has not arrived yet.
     *
     * @return array{orders: int, amount: float, commission: float, oldest: string|null}
     */
    public function receivable(): array
    {
        $row = Order::query()
            ->where('status', OrderStatus::Delivered->value)
            ->where('payment_status', PaymentStatus::Pending->value)
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(total), 0) as amount')
            ->selectRaw('COALESCE(SUM(commission), 0) as commission')
            ->selectRaw('MIN(delivered_at) as oldest')
            ->toBase()
            ->first();

        return [
            'orders' => (int) ($row->orders_count ?? 0),
            'amount' => round((float) ($row->amount ?? 0), 2),
            'commission' => round((float) ($row->commission ?? 0), 2),
            'oldest' => $row?->oldest === null ? null : CarbonImmutable::parse($row->oldest)->toDateString(),
        ];
    }

    /**
     * How long an errand takes, from the moment it is placed to the moment it lands.
     * Counted on the delivery date, since that is when the time was actually spent,
     * and worked out in PHP so the figure does not depend on the database engine's
     * date arithmetic.
     *
     * @return array{orders: int, average: int|null, slowest: int|null}
     */
    public function deliveryTimes(): array
    {
        $minutes = $this->deliveryMinutes()->flatten();

        return [
            'orders' => $minutes->count(),
            'average' => $minutes->isEmpty() ? null : (int) round((float) $minutes->avg()),
            'slowest' => $minutes->isEmpty() ? null : (int) $minutes->max(),
        ];
    }

    /**
     * Delivery times in minutes, grouped by courier so both the overall figure and
     * the per-courier one come from a single pass over the same orders.
     *
     * @return Collection<int|string, Collection<int, int<0, max>>>
     */
    private function deliveryMinutes(): Collection
    {
        return Order::query()
            ->whereNotNull('delivered_at')
            ->whereBetween('delivered_at', [$this->from, $this->to])
            ->toBase()
            ->get(['courier_id', 'created_at', 'delivered_at'])
            ->groupBy(fn (object $row): string => (string) ($row->courier_id ?? ''))
            ->map(fn (Collection $rows): Collection => $rows
                ->map(fn (object $row): int => (int) CarbonImmutable::parse($row->created_at)
                    ->diffInMinutes(CarbonImmutable::parse($row->delivered_at)))
                ->filter(fn (int $minutes): bool => $minutes >= 0)
                ->values());
    }

    /**
     * @return array<int, array{date: string, orders: int, revenue: float}>
     */
    public function perDay(): array
    {
        return Order::query()
            ->whereBetween('created_at', [$this->from, $this->to])
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(commission), 0) as commission')
            ->groupBy('day')
            ->orderBy('day')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'date' => (string) $row->day,
                'orders' => (int) $row->orders_count,
                'revenue' => round((float) $row->commission, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * Couriers ranked by the fees they brought in, not by the money they carried:
     * the shopping bill belongs to the shop, and sorting on it would put whoever
     * delivered the most expensive groceries at the top of the table.
     *
     * @return array<int, CourierRow>
     */
    public function perCourier(): array
    {
        $rows = Order::query()
            ->whereBetween('created_at', [$this->from, $this->to])
            ->whereNotNull('courier_id')
            ->selectRaw('courier_id')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw("SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'delivered' THEN commission ELSE 0 END), 0) as commission")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'delivered' THEN total ELSE 0 END), 0) as moved")
            ->groupBy('courier_id')
            ->toBase()
            ->get();

        $names = User::whereIn('id', $rows->pluck('courier_id'))->pluck('name', 'id');
        $minutes = $this->deliveryMinutes();

        return $rows
            ->map(/** @return CourierRow */ function (object $row) use ($names, $minutes): array {
                $own = $minutes->get((string) $row->courier_id);

                return [
                    'courier' => (string) ($names[$row->courier_id] ?? 'N/D'),
                    'orders' => (int) $row->orders_count,
                    'delivered' => (int) $row->delivered_count,
                    'commission' => round((float) $row->commission, 2),
                    'moved' => round((float) $row->moved, 2),
                    'average' => $own === null || $own->isEmpty()
                        ? null
                        : (int) round((float) $own->avg()),
                ];
            })
            ->sortByDesc('commission')
            ->values()
            ->all();
    }

    /**
     * Who the business actually lives off. Ranked by the fees they leave behind,
     * with the date of their last order so a good client going quiet is visible.
     *
     * @return array<int, array{client: string, orders: int, commission: float, moved: float, last: string}>
     */
    public function perClient(): array
    {
        $rows = Order::query()
            ->whereBetween('created_at', [$this->from, $this->to])
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->selectRaw('client_id')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(commission), 0) as commission')
            ->selectRaw('COALESCE(SUM(total), 0) as moved')
            ->selectRaw('MAX(created_at) as last_order')
            ->groupBy('client_id')
            ->orderByDesc('commission')
            ->orderByDesc('orders_count')
            ->limit(self::TOP_CLIENTS)
            ->toBase()
            ->get();

        $names = Client::whereIn('id', $rows->pluck('client_id'))->pluck('name', 'id');

        return $rows->map(fn (object $row): array => [
            'client' => (string) ($names[$row->client_id] ?? 'N/D'),
            'orders' => (int) $row->orders_count,
            'commission' => round((float) $row->commission, 2),
            'moved' => round((float) $row->moved, 2),
            'last' => CarbonImmutable::parse($row->last_order)->toDateString(),
        ])->values()->all();
    }

    /**
     * @return array<int, array{method: string, orders: int, amount: float}>
     */
    public function perMethod(): array
    {
        return Order::query()
            ->where('payment_status', PaymentStatus::Paid->value)
            ->whereBetween('paid_at', [$this->from, $this->to])
            ->selectRaw('payment_method')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(total), 0) as amount')
            ->groupBy('payment_method')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'method' => PaymentMethod::tryFrom((string) $row->payment_method)?->label() ?? 'N/D',
                'orders' => (int) $row->orders_count,
                'amount' => round((float) $row->amount, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{name: string, quantity: float, spent: float, orders: int}>
     */
    public function topProducts(): array
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$this->from, $this->to])
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->selectRaw('order_items.name as name')
            ->selectRaw('SUM(order_items.quantity) as total_quantity')
            ->selectRaw('SUM(COALESCE(order_items.line_total, 0)) as total_spent')
            ->selectRaw('COUNT(DISTINCT order_items.order_id) as orders_count')
            ->groupBy('order_items.name')
            ->orderByDesc('total_spent')
            ->orderByDesc('total_quantity')
            ->limit(self::TOP_PRODUCTS)
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'name' => (string) $row->name,
                'quantity' => round((float) $row->total_quantity, 2),
                'spent' => round((float) $row->total_spent, 2),
                'orders' => (int) $row->orders_count,
            ])
            ->values()
            ->all();
    }

    /**
     * How this period compares with the one before it, as a percentage. Null when
     * there is nothing to compare against: "up from zero" is not a percentage, and
     * printing one would invent a trend out of a first sale.
     */
    private function change(float $current, float $previous): ?float
    {
        if ($previous <= 0.0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
