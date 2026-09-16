<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * @phpstan-type CashCutRow array{courier: string, cash: float, transfer: float, total: float, commission: float, orders: int<0, max>}
 */
class DashboardController extends Controller
{
    /**
     * How the orders-over-time chart can be sliced. "day" and "month" are windows
     * ending today; "custom" is whatever range the admin picked.
     */
    private const CHART_PERIODS = ['day', 'month', 'custom'];

    private const CHART_DAYS = 14;

    private const CHART_MONTHS = 12;

    /**
     * Past this many days a range stops reading as daily bars and gets rolled up
     * into months instead.
     */
    private const CHART_MAX_DAILY_BARS = 62;

    /**
     * A range nobody can read is a range nobody asked for.
     */
    private const CHART_MAX_YEARS = 5;

    /**
     * Past this many hours an open order has been sitting long enough that the admin
     * should look at it before a client complains.
     */
    private const STALE_HOURS = 2;

    /**
     * How close each open status is to delivery. Higher wins when picking what the
     * courier should look at first.
     */
    private const URGENCY = [
        'requested' => 0,
        'confirmed' => 1,
        'assigned' => 2,
        'purchasing' => 3,
        'purchased' => 4,
        'on_the_way' => 5,
    ];

    /**
     * The single transition the courier can make from each open status, so the panel
     * can offer it as one tap instead of sending them into the order first.
     */
    private const NEXT_STEP = [
        'requested' => ['status' => 'purchasing', 'label' => 'Empezar la compra'],
        'confirmed' => ['status' => 'purchasing', 'label' => 'Empezar la compra'],
        'assigned' => ['status' => 'purchasing', 'label' => 'Empezar la compra'],
        'purchasing' => ['status' => 'purchased', 'label' => 'Ya compré'],
        'purchased' => ['status' => 'on_the_way', 'label' => 'Voy en camino'],
        'on_the_way' => ['status' => 'delivered', 'label' => 'Entregado'],
    ];

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            return Inertia::render('Dashboard', $this->courierPanel($user));
        }

        $request->validate([
            'period' => ['nullable', Rule::in(self::CHART_PERIODS)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $today = today();

        $statusCounts = Order::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $openByStatus = collect(OrderStatus::cases())
            ->filter(fn (OrderStatus $status): bool => $status->isOpen())
            ->map(fn (OrderStatus $status): array => [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => (int) ($statusCounts[$status->value] ?? 0),
            ])
            ->values();

        $stats = [
            'today' => Order::whereDate('created_at', $today)->count(),
            'open' => Order::query()->open()->count(),
            'deliveredToday' => Order::where('status', OrderStatus::Delivered->value)
                ->whereDate('delivered_at', $today)
                ->count(),
            'unpaidDelivered' => Order::where('status', OrderStatus::Delivered->value)
                ->where('payment_status', PaymentStatus::Pending->value)
                ->count(),
        ];

        // What is waiting on the admin, not on the courier: orders with nobody assigned
        // yet, and open orders that have been sitting too long.
        $attention = [
            'unassigned' => Order::query()->open()->whereNull('courier_id')->count(),
            'stale' => Order::query()->open()
                ->where('created_at', '<=', now()->subHours(self::STALE_HOURS))
                ->count(),
        ];

        [$corte, $corteTotals] = $this->dailyCashCut($today);

        return Inertia::render('Dashboard', [
            'isAdmin' => true,
            // The stat cards link into the order list, and the filters there run on
            // dates, not on the browser's clock: the day has to come from the server
            // that counted them.
            'today' => $today->toDateString(),
            'stats' => $stats,
            'attention' => $attention,
            'staleHours' => self::STALE_HOURS,
            // The money three ways: what the business earned today, what it is owed,
            // and what is still riding around with the couriers.
            'finance' => [
                'commissionToday' => $corteTotals['commission'],
                'receivable' => $this->receivable($today),
                'inTheStreet' => $this->inTheStreet(),
            ],
            'openByStatus' => $openByStatus,
            'ordersChart' => $this->ordersChart($request, $today),
            'corte' => $corte,
            'corteTotals' => $corteTotals,
        ]);
    }

    /**
     * Orders over time. The admin picks the window; how the window is bucketed is
     * not their problem, so a range too long to read as daily bars comes back as
     * months instead, and the response says which so the chart can label it.
     *
     * @return array{period: string, unit: string, from: string, to: string, points: list<array{date: string, orders: int, revenue: float}>}
     */
    private function ordersChart(Request $request, CarbonInterface $today): array
    {
        $period = $request->string('period')->toString();
        $period = in_array($period, self::CHART_PERIODS, true) ? $period : 'day';

        [$from, $to] = match ($period) {
            'month' => [$today->startOfMonth()->subMonths(self::CHART_MONTHS - 1), $today],
            'custom' => $this->customChartRange($request, $today),
            default => [$today->subDays(self::CHART_DAYS - 1), $today],
        };

        $unit = $period === 'month' || $from->diffInDays($to) >= self::CHART_MAX_DAILY_BARS
            ? 'month'
            : 'day';

        $totals = Order::query()
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw("SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as revenue")
            ->groupBy('day')
            ->toBase()
            ->get()
            ->groupBy(fn (object $row): string => $unit === 'month'
                ? substr((string) $row->day, 0, 7)
                : (string) $row->day);

        $points = [];
        $cursor = $unit === 'month' ? $from->startOfMonth() : $from->startOfDay();
        $last = $unit === 'month' ? $to->startOfMonth() : $to->startOfDay();

        while ($cursor->lessThanOrEqualTo($last)) {
            $bucket = $totals->get($cursor->format($unit === 'month' ? 'Y-m' : 'Y-m-d'));

            $points[] = [
                'date' => $cursor->toDateString(),
                'orders' => (int) ($bucket?->sum(fn (object $row): int => (int) $row->orders_count) ?? 0),
                'revenue' => round((float) ($bucket?->sum(fn (object $row): float => (float) $row->revenue) ?? 0), 2),
            ];

            $cursor = $unit === 'month' ? $cursor->addMonth() : $cursor->addDay();
        }

        return [
            'period' => $period,
            'unit' => $unit,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'points' => $points,
        ];
    }

    /**
     * The range behind the custom period: whatever the admin picked, put back in
     * order if they picked it backwards and trimmed to something a chart can still
     * draw. Both ends show in the UI, so the trimming is never a silent one.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function customChartRange(Request $request, CarbonInterface $today): array
    {
        $from = $request->date('from') ?? $today->subDays(self::CHART_DAYS - 1);
        $to = $request->date('to') ?? $today;

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $earliest = $to->subYears(self::CHART_MAX_YEARS);

        return [$from->lessThan($earliest) ? $earliest : $from, $to];
    }

    /**
     * The courier panel answers two questions, in this order: what do I do next, and
     * how much of the business's money am I carrying. Counts alone are not actionable,
     * so the open orders come back as a concrete next step plus buckets to tap into.
     *
     * @return array<string, mixed>
     */
    private function courierPanel(User $user): array
    {
        $open = $user->assignedOrders()
            ->with(['client:id,name', 'address:id,street,neighborhood'])
            ->open()
            ->get();

        $today = today();

        return [
            'isAdmin' => false,
            'courierOpenOrders' => $open->count(),
            'nextOrder' => $this->nextCourierOrder($open),
            'buckets' => [
                'to_buy' => $open->filter(fn (Order $order): bool => in_array($order->status, [
                    OrderStatus::Requested, OrderStatus::Confirmed, OrderStatus::Assigned,
                ], true))->count(),
                'buying' => $open->where('status', OrderStatus::Purchasing)->count(),
                'to_deliver' => $open->filter(fn (Order $order): bool => in_array($order->status, [
                    OrderStatus::Purchased, OrderStatus::OnTheWay,
                ], true))->count(),
            ],
            'deliveredToday' => $user->assignedOrders()
                ->whereDate('delivered_at', $today)
                ->count(),
            // Only the admin registers payments, so this trails whatever they have
            // captured so far. The UI says as much rather than passing it off as live.
            'cashCollectedToday' => round((float) $user->assignedOrders()
                ->where('payment_status', PaymentStatus::Paid->value)
                ->where('payment_method', PaymentMethod::Cash->value)
                ->whereDate('paid_at', $today)
                ->sum('total'), 2),
            'awaitingPayment' => $user->assignedOrders()
                ->where('status', OrderStatus::Delivered->value)
                ->where('payment_status', PaymentStatus::Pending->value)
                ->count(),
        ];
    }

    /**
     * The order to surface first: whichever is closest to being delivered, because a
     * client is already waiting on it, and the oldest one when several tie.
     *
     * @param  Collection<int, Order>  $open
     * @return array<string, mixed>|null
     */
    private function nextCourierOrder(Collection $open): ?array
    {
        $order = $open
            ->sortBy([
                fn (Order $a, Order $b): int => (self::URGENCY[$b->status->value] ?? 0) <=> (self::URGENCY[$a->status->value] ?? 0),
                fn (Order $a, Order $b): int => $a->created_at <=> $b->created_at,
            ])
            ->first();

        if ($order === null) {
            return null;
        }

        $step = self::NEXT_STEP[$order->status->value] ?? null;

        return [
            'id' => $order->id,
            'client' => $order->client?->name,
            'status_label' => $order->status->label(),
            'address' => collect([$order->address?->street, $order->address?->neighborhood])
                ->filter()
                ->implode(', '),
            'next_status' => $step['status'] ?? null,
            'next_label' => $step['label'] ?? null,
        ];
    }

    /**
     * Money already earned and still outside the cash box: orders delivered but not
     * paid for. It ignores any date window on purpose — a debt does not age out — and
     * reports how old the oldest one is so a debt left sitting stands out.
     *
     * @return array{orders: int, amount: float, oldestDays: int|null}
     */
    private function receivable(CarbonInterface $today): array
    {
        $row = Order::query()
            ->where('status', OrderStatus::Delivered->value)
            ->where('payment_status', PaymentStatus::Pending->value)
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(total), 0) as amount')
            ->selectRaw('MIN(delivered_at) as oldest')
            ->toBase()
            ->first();

        return [
            'orders' => (int) ($row->orders_count ?? 0),
            'amount' => round((float) ($row->amount ?? 0), 2),
            'oldestDays' => $row?->oldest === null
                ? null
                : (int) CarbonImmutable::parse($row->oldest)->startOfDay()->diffInDays($today->startOfDay()),
        ];
    }

    /**
     * The value of orders already bought and on their way but not yet paid: the
     * business's money the couriers are carrying right now.
     *
     * @return array{orders: int, amount: float}
     */
    private function inTheStreet(): array
    {
        $row = Order::query()
            ->whereIn('status', [OrderStatus::Purchased->value, OrderStatus::OnTheWay->value])
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(total), 0) as amount')
            ->toBase()
            ->first();

        return [
            'orders' => (int) ($row->orders_count ?? 0),
            'amount' => round((float) ($row->amount ?? 0), 2),
        ];
    }

    /**
     * Build the daily cash cut: money collected today grouped by courier.
     *
     * @return array{0: Collection<int, CashCutRow>, 1: array<string, float>}
     */
    private function dailyCashCut(CarbonInterface $day): array
    {
        $paid = Order::query()
            ->with('courier:id,name')
            ->where('payment_status', PaymentStatus::Paid->value)
            ->whereDate('paid_at', $day)
            ->get(['id', 'courier_id', 'payment_method', 'total', 'commission']);

        $corte = $paid
            ->groupBy(fn (Order $order): string => $order->courier->name ?? 'Sin asignar')
            ->map(/** @return CashCutRow */ function (Collection $orders, string $courier): array {
                $cash = $orders->filter(fn (Order $o): bool => $o->payment_method === PaymentMethod::Cash)
                    ->sum(fn (Order $o): float => (float) $o->total);
                $transfer = $orders->filter(fn (Order $o): bool => $o->payment_method === PaymentMethod::Transfer)
                    ->sum(fn (Order $o): float => (float) $o->total);

                return [
                    'courier' => $courier,
                    'cash' => round($cash, 2),
                    'transfer' => round($transfer, 2),
                    'total' => round($cash + $transfer, 2),
                    'commission' => round($orders->sum(fn (Order $o): float => (float) $o->commission), 2),
                    'orders' => $orders->count(),
                ];
            })
            ->values();

        $corteTotals = [
            'cash' => round((float) $corte->sum('cash'), 2),
            'transfer' => round((float) $corte->sum('transfer'), 2),
            'total' => round((float) $corte->sum('total'), 2),
            'commission' => round((float) $corte->sum('commission'), 2),
        ];

        return [$corte, $corteTotals];
    }
}
