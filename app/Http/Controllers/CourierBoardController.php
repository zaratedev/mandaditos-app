<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderDelivered;
use App\Support\AppDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CourierBoardController extends Controller
{
    /**
     * The operational statuses a courier is allowed to set.
     */
    private const COURIER_STATUSES = [
        OrderStatus::Purchasing,
        OrderStatus::Purchased,
        OrderStatus::OnTheWay,
        OrderStatus::Delivered,
    ];

    public function index(Request $request): Response
    {
        $orders = $request->user()->assignedOrders()
            ->with('client:id,name')
            ->open()
            ->latest()
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'client' => $order->client?->name,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'payment_status' => $order->payment_status->value,
                'total' => $order->total,
                'created_at' => AppDate::dateTime($order->created_at),
            ]);

        return Inertia::render('board/Index', [
            'orders' => $orders,
        ]);
    }

    public function show(Order $order): Response
    {
        $order->load(['client', 'address', 'items']);

        return Inertia::render('board/Show', [
            'order' => [
                'id' => $order->id,
                'shopping_list' => $order->shopping_list,
                'notes' => $order->notes,
                'items_subtotal' => $order->items_subtotal,
                'commission' => $order->commission,
                'total' => $order->total,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'payment_status' => $order->payment_status->value,
                'payment_status_label' => $order->payment_status->label(),
                'client' => $order->client->only(['id', 'name', 'phone']),
                'address' => $order->address->only(['id', 'label', 'street', 'neighborhood', 'city', 'landmark']),
                'items' => $order->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                ]),
            ],
            'statuses' => $this->courierStatusOptions(),
        ]);
    }

    public function advanceStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in($this->courierStatusValues())],
        ]);

        $status = OrderStatus::from($validated['status']);
        $wasDelivered = $order->status === OrderStatus::Delivered;
        $order->status = $status;

        match ($status) {
            OrderStatus::Purchased => $order->purchased_at ??= now(),
            OrderStatus::Delivered => $order->delivered_at ??= now(),
            default => null,
        };

        $order->save();

        if ($status === OrderStatus::Delivered && ! $wasDelivered) {
            Notification::send(
                User::where('role', UserRole::Admin->value)->get(),
                new OrderDelivered($order),
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Status updated.')]);

        return back();
    }

    /**
     * @return array<int, string>
     */
    private function courierStatusValues(): array
    {
        return array_map(fn (OrderStatus $status): string => $status->value, self::COURIER_STATUSES);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function courierStatusOptions(): array
    {
        return array_map(
            fn (OrderStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            self::COURIER_STATUSES,
        );
    }
}
