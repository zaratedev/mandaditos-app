<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * @phpstan-type CashCutRow array{courier: string, cash: float, transfer: float, total: float, commission: float, orders: int<0, max>}
 */
class DashboardController extends Controller
{
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

        $since = $today->copy()->subDays(13);

        $perDay = Order::query()
            ->where('created_at', '>=', $since->startOfDay())
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw("SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as revenue")
            ->groupBy('day')
            ->toBase()
            ->get()
            ->keyBy('day');

        $ordersPerDay = collect(range(0, 13))
            ->map(function (int $offset) use ($since, $perDay): array {
                $date = $since->addDays($offset)->toDateString();
                $row = $perDay->get($date);

                return [
                    'day' => $date,
                    'orders' => $row ? (int) $row->orders_count : 0,
                    'revenue' => $row ? round((float) $row->revenue, 2) : 0.0,
                ];
            });

        $stats = [
            'today' => Order::whereDate('created_at', $today)->count(),
            'open' => Order::whereNotIn('status', [OrderStatus::Delivered->value, OrderStatus::Cancelled->value])->count(),
            'deliveredToday' => Order::where('status', OrderStatus::Delivered->value)
                ->whereDate('delivered_at', $today)
                ->count(),
            'unpaidDelivered' => Order::where('status', OrderStatus::Delivered->value)
                ->where('payment_status', PaymentStatus::Pending->value)
                ->count(),
        ];

        [$corte, $corteTotals] = $this->dailyCashCut($today);

        return Inertia::render('Dashboard', [
            'isAdmin' => true,
            'stats' => $stats,
            'openByStatus' => $openByStatus,
            'ordersPerDay' => $ordersPerDay,
            'corte' => $corte,
            'corteTotals' => $corteTotals,
        ]);
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
            ->whereNotIn('status', [OrderStatus::Delivered->value, OrderStatus::Cancelled->value])
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
