<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $fromDate = isset($validated['from']) ? Carbon::parse($validated['from']) : Carbon::now()->startOfMonth();
        $toDate = isset($validated['to']) ? Carbon::parse($validated['to']) : Carbon::now();

        if ($fromDate->greaterThan($toDate)) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $from = $fromDate->copy()->startOfDay();
        $to = $toDate->copy()->endOfDay();

        $base = fn () => Order::query()->whereBetween('created_at', [$from, $to]);
        $paid = fn () => $base()->where('payment_status', PaymentStatus::Paid->value);

        $summary = [
            'orders' => $base()->count(),
            'delivered' => $base()->where('status', OrderStatus::Delivered->value)->count(),
            'cancelled' => $base()->where('status', OrderStatus::Cancelled->value)->count(),
            'unpaid' => $base()
                ->where('payment_status', PaymentStatus::Pending->value)
                ->where('status', '!=', OrderStatus::Cancelled->value)
                ->count(),
        ];

        $money = [
            'revenue' => round((float) $paid()->sum('total'), 2),
            'commission' => round((float) $paid()->sum('commission'), 2),
            'cash' => round((float) $paid()->where('payment_method', 'cash')->sum('total'), 2),
            'transfer' => round((float) $paid()->where('payment_method', 'transfer')->sum('total'), 2),
            'paidOrders' => $paid()->count(),
        ];

        $perDay = $base()
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw("SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as revenue")
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($row): array => [
                'day' => $row->day,
                'orders' => (int) $row->orders_count,
                'revenue' => round((float) $row->revenue, 2),
            ]);

        $courierRows = $base()
            ->whereNotNull('courier_id')
            ->selectRaw('courier_id')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw("SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_count")
            ->selectRaw("SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as revenue")
            ->selectRaw("SUM(CASE WHEN payment_status = 'paid' THEN commission ELSE 0 END) as commission")
            ->groupBy('courier_id')
            ->get();

        $courierNames = User::whereIn('id', $courierRows->pluck('courier_id'))->pluck('name', 'id');

        $perCourier = $courierRows
            ->map(fn ($row): array => [
                'courier' => $courierNames[$row->courier_id] ?? 'N/D',
                'orders' => (int) $row->orders_count,
                'delivered' => (int) $row->delivered_count,
                'revenue' => round((float) $row->revenue, 2),
                'commission' => round((float) $row->commission, 2),
            ])
            ->sortByDesc('revenue')
            ->values();

        $perMethod = $paid()
            ->selectRaw('payment_method')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('SUM(total) as revenue')
            ->groupBy('payment_method')
            ->get()
            ->map(fn ($row): array => [
                'method' => $row->payment_method?->label() ?? 'N/D',
                'orders' => (int) $row->orders_count,
                'revenue' => round((float) $row->revenue, 2),
            ]);

        return Inertia::render('reports/Index', [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'summary' => $summary,
            'totals' => $money,
            'perDay' => $perDay,
            'perCourier' => $perCourier,
            'perMethod' => $perMethod,
        ]);
    }
}
