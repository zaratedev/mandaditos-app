<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            return Inertia::render('Dashboard', [
                'isAdmin' => false,
                'courierOpenOrders' => $user->assignedOrders()
                    ->whereNotIn('status', [OrderStatus::Delivered->value, OrderStatus::Cancelled->value])
                    ->count(),
            ]);
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
     * Build the daily cash cut: money collected today grouped by courier.
     *
     * @return array{0: Collection<int, array<string, mixed>>, 1: array<string, float>}
     */
    private function dailyCashCut(\Carbon\CarbonInterface $day): array
    {
        $paid = Order::query()
            ->with('courier:id,name')
            ->where('payment_status', PaymentStatus::Paid->value)
            ->whereDate('paid_at', $day)
            ->get(['id', 'courier_id', 'payment_method', 'total', 'commission']);

        $corte = $paid
            ->groupBy(fn (Order $order): string => $order->courier?->name ?? 'Sin asignar')
            ->map(function (Collection $orders, string $courier): array {
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
