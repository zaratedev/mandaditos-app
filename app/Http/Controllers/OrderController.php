<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Http\Requests\AssignOrderRequest;
use App\Http\Requests\RecordPurchaseRequest;
use App\Http\Requests\RegisterPaymentRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderAssigned;
use App\Notifications\OrderDelivered;
use App\Support\AppDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * Pseudo status for the list filter: every order that is still in flight. It is
     * not an OrderStatus, so it only ever reaches the query through this constant.
     */
    private const STATUS_OPEN = 'open';

    /**
     * Pseudo status for the list filter: orders already bought and on their way but
     * not yet delivered — the money the couriers are carrying. Like STATUS_OPEN it
     * spans several real statuses and only reaches the query through this constant.
     */
    private const STATUS_IN_TRANSIT = 'in_transit';

    /**
     * Which date the from/to range filters on. An order carries several dates and
     * "delivered today" is a different question than "created today".
     */
    private const DATE_FIELDS = [
        'created' => ['column' => 'created_at', 'label' => 'Creación'],
        'delivered' => ['column' => 'delivered_at', 'label' => 'Entrega'],
        'paid' => ['column' => 'paid_at', 'label' => 'Pago'],
    ];

    /**
     * How many orders a page can hold, and the default when the request asks for
     * something that is not on the menu.
     */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    private const DEFAULT_PER_PAGE = 25;

    public function index(Request $request): Response
    {
        $request->validate([
            'status' => ['nullable', Rule::in(array_column($this->filterStatusOptions(), 'value'))],
            'client_id' => ['nullable', 'integer'],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'date_field' => ['nullable', Rule::in(array_keys(self::DATE_FIELDS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', Rule::in(self::PER_PAGE_OPTIONS)],
        ]);

        $perPage = in_array($request->integer('per_page'), self::PER_PAGE_OPTIONS, true)
            ? $request->integer('per_page')
            : self::DEFAULT_PER_PAGE;

        $filters = [
            'status' => $request->string('status')->toString(),
            'courier_id' => $request->string('courier_id')->toString(),
            'client_id' => $request->string('client_id')->toString(),
            'payment_status' => $request->string('payment_status')->toString(),
            'payment_method' => $request->string('payment_method')->toString(),
            'date_field' => $request->string('date_field')->toString() ?: 'created',
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
        ];

        $dateColumn = (self::DATE_FIELDS[$filters['date_field']] ?? self::DATE_FIELDS['created'])['column'];

        $orders = Order::query()
            ->with(['client:id,name', 'courier:id,name'])
            ->when($filters['status'] === self::STATUS_OPEN, fn ($query) => $query->open())
            ->when(
                $filters['status'] === self::STATUS_IN_TRANSIT,
                fn ($query) => $query->whereIn('status', [
                    OrderStatus::Purchased->value, OrderStatus::OnTheWay->value,
                ]),
            )
            ->when(
                $filters['status'] !== ''
                    && ! in_array($filters['status'], [self::STATUS_OPEN, self::STATUS_IN_TRANSIT], true),
                fn ($query) => $query->where('status', $filters['status']),
            )
            ->when($filters['courier_id'] === 'unassigned', fn ($query) => $query->whereNull('courier_id'))
            ->when(
                $filters['courier_id'] !== '' && $filters['courier_id'] !== 'unassigned',
                fn ($query) => $query->where('courier_id', $filters['courier_id']),
            )
            ->when($filters['client_id'] !== '', fn ($query) => $query->where('client_id', $filters['client_id']))
            ->when($filters['payment_status'] !== '', fn ($query) => $query->where('payment_status', $filters['payment_status']))
            ->when($filters['payment_method'] !== '', fn ($query) => $query->where('payment_method', $filters['payment_method']))
            ->when($filters['from'] !== '', fn ($query) => $query->whereDate($dateColumn, '>=', $filters['from']))
            ->when($filters['to'] !== '', fn ($query) => $query->whereDate($dateColumn, '<=', $filters['to']))
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Order $order): array => [
                'id' => $order->id,
                'client' => $order->client?->name,
                'courier' => $order->courier?->name,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'total' => $order->total,
                'payment_status' => $order->payment_status->value,
                'payment_status_label' => $order->payment_status->label(),
                'created_at' => AppDate::dateTime($order->created_at),
            ]);

        return Inertia::render('orders/Index', [
            'orders' => $orders,
            'filters' => $filters,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'statuses' => $this->filterStatusOptions(),
            'dateFields' => $this->dateFieldOptions(),
            'couriers' => $this->couriers(),
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
            'paymentStatuses' => collect(PaymentStatus::cases())
                ->map(fn (PaymentStatus $status): array => ['value' => $status->value, 'label' => $status->label()])
                ->all(),
            'paymentMethods' => collect(PaymentMethod::cases())
                ->map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()])
                ->all(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('orders/Create', [
            'clients' => Client::query()
                ->active()
                ->with('addresses:id,client_id,label,street,neighborhood,city')
                ->orderBy('name')
                ->get(['id', 'name', 'phone']),
            'couriers' => $this->assignableCouriers(),
        ]);
    }

    public function store(StoreOrderRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $order = DB::transaction(function () use ($data, $request): Order {
            $order = Order::create([
                'client_id' => $data['client_id'],
                'address_id' => $data['address_id'],
                'courier_id' => $data['courier_id'] ?? null,
                'status' => isset($data['courier_id']) ? OrderStatus::Assigned : OrderStatus::Requested,
                'shopping_list' => $data['shopping_list'],
                'commission' => $data['commission'] ?? null,
                'notes' => $data['notes'] ?? null,
                'payment_status' => PaymentStatus::Pending,
                'created_by' => $request->user()->id,
                'confirmed_at' => isset($data['courier_id']) ? now() : null,
            ]);

            foreach ($data['items'] ?? [] as $item) {
                $quantity = (float) ($item['quantity'] ?? 1);
                $unitPrice = isset($item['unit_price']) ? (float) $item['unit_price'] : null;

                $order->items()->create([
                    'name' => $item['name'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $unitPrice !== null ? round($quantity * $unitPrice, 2) : null,
                ]);
            }

            $order->recalculateTotals();

            return $order;
        });

        if ($order->courier_id !== null) {
            User::find($order->courier_id)?->notify(new OrderAssigned($order));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Order created.')]);

        return redirect()->route('orders.show', $order);
    }

    public function show(Order $order): Response
    {
        $order->load(['client', 'address', 'courier:id,name', 'creator:id,name', 'items']);

        return Inertia::render('orders/Show', [
            'order' => [
                'id' => $order->id,
                'shopping_list' => $order->shopping_list,
                'notes' => $order->notes,
                'items_subtotal' => $order->items_subtotal,
                'commission' => $order->commission,
                'total' => $order->total,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'payment_method' => $order->payment_method?->value,
                'payment_method_label' => $order->payment_method?->label(),
                'payment_status' => $order->payment_status->value,
                'payment_status_label' => $order->payment_status->label(),
                'client' => $order->client->only(['id', 'name', 'phone']),
                'address' => $order->address->only(['id', 'label', 'street', 'neighborhood', 'city', 'landmark']),
                'courier' => $order->courier?->only(['id', 'name']),
                'creator' => $order->creator?->only(['id', 'name']),
                'items' => $order->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                ]),
                'created_at' => AppDate::dateTime($order->created_at),
                'confirmed_at' => AppDate::dateTime($order->confirmed_at),
                'purchased_at' => AppDate::dateTime($order->purchased_at),
                'delivered_at' => AppDate::dateTime($order->delivered_at),
                'paid_at' => AppDate::dateTime($order->paid_at),
            ],
            'couriers' => $this->assignableCouriers($order->courier_id),
            'statuses' => $this->statusOptions(),
            'paymentMethods' => collect(PaymentMethod::cases())
                ->map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()])
                ->all(),
        ]);
    }

    public function edit(Order $order): Response
    {
        $order->load(['client:id,name', 'items']);

        return Inertia::render('orders/Edit', [
            'order' => [
                'id' => $order->id,
                'client' => $order->client->only(['id', 'name']),
                'address_id' => $order->address_id,
                'courier_id' => $order->courier_id,
                'shopping_list' => $order->shopping_list,
                'commission' => $order->commission,
                'notes' => $order->notes,
                'items' => $order->items->map(fn ($item): array => [
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                ]),
            ],
            'addresses' => $order->client->addresses()
                ->orderBy('id')
                ->get(['id', 'label', 'street', 'neighborhood', 'city']),
            'couriers' => $this->assignableCouriers($order->courier_id),
        ]);
    }

    public function update(UpdateOrderRequest $request, Order $order): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $order): void {
            $order->update([
                'address_id' => $data['address_id'],
                'courier_id' => $data['courier_id'] ?? null,
                'shopping_list' => $data['shopping_list'],
                'commission' => $data['commission'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $order->items()->delete();

            foreach ($data['items'] ?? [] as $item) {
                $quantity = (float) ($item['quantity'] ?? 1);
                $unitPrice = isset($item['unit_price']) ? (float) $item['unit_price'] : null;

                $order->items()->create([
                    'name' => $item['name'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $unitPrice !== null ? round($quantity * $unitPrice, 2) : null,
                ]);
            }

            $order->recalculateTotals();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Order updated.')]);

        return redirect()->route('orders.show', $order);
    }

    public function cancel(Order $order): RedirectResponse
    {
        $order->status = OrderStatus::Cancelled;
        $order->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Order cancelled.')]);

        return redirect()->route('orders.index');
    }

    public function assign(AssignOrderRequest $request, Order $order): RedirectResponse
    {
        $order->courier_id = $request->integer('courier_id');

        if (in_array($order->status, [OrderStatus::Requested, OrderStatus::Confirmed], true)) {
            $order->status = OrderStatus::Assigned;
        }

        $order->confirmed_at ??= now();
        $order->save();

        User::find($order->courier_id)?->notify(new OrderAssigned($order));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Courier assigned.')]);

        return back();
    }

    public function advanceStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
        ]);

        $status = OrderStatus::from($validated['status']);
        $wasDelivered = $order->status === OrderStatus::Delivered;
        $order->status = $status;

        match ($status) {
            OrderStatus::Confirmed => $order->confirmed_at ??= now(),
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

    public function registerPayment(RegisterPaymentRequest $request, Order $order): RedirectResponse
    {
        $order->payment_method = PaymentMethod::from($request->string('payment_method')->toString());
        $order->payment_status = PaymentStatus::Paid;
        $order->paid_at = now();
        $order->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment recorded.')]);

        return back();
    }

    /**
     * Record the actual purchase amount and commission after the courier shops.
     */
    public function recordPurchase(RecordPurchaseRequest $request, Order $order): RedirectResponse
    {
        $data = $request->validated();

        $order->items_subtotal = $data['items_subtotal'];
        $order->commission = $data['commission'];
        $order->total = round((float) $data['items_subtotal'] + (float) $data['commission'], 2);
        $order->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Purchase recorded.')]);

        return back();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return collect(OrderStatus::cases())
            ->map(fn (OrderStatus $status): array => ['value' => $status->value, 'label' => $status->label()])
            ->all();
    }

    /**
     * The list filter also offers "open", which spans several statuses. It stays out
     * of statusOptions() so the status pickers on the order pages keep only real
     * statuses.
     *
     * @return list<array{value: string, label: string}>
     */
    private function filterStatusOptions(): array
    {
        return [
            ['value' => self::STATUS_OPEN, 'label' => 'Abiertos'],
            ['value' => self::STATUS_IN_TRANSIT, 'label' => 'En la calle'],
            ...$this->statusOptions(),
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function dateFieldOptions(): array
    {
        return collect(self::DATE_FIELDS)
            ->map(fn (array $field, string $value): array => ['value' => $value, 'label' => $field['label']])
            ->values()
            ->all();
    }

    /**
     * Every courier, active or not, so the list filters can still reach the orders of
     * someone who has since been deactivated.
     *
     * @return Collection<int, User>
     */
    private function couriers(): Collection
    {
        return User::query()
            ->couriers()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Couriers that may take work: the active ones, plus the courier already assigned
     * to the order being edited, so a deactivation never blocks an unrelated edit.
     *
     * @return Collection<int, User>
     */
    private function assignableCouriers(?int $keepId = null): Collection
    {
        return User::query()
            ->couriers()
            ->where(fn (Builder $query) => $query
                ->where('is_active', true)
                ->when($keepId !== null, fn (Builder $inner) => $inner->orWhere('id', $keepId)))
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);
    }
}
