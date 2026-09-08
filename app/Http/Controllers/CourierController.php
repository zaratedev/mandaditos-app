<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Requests\StoreCourierRequest;
use App\Http\Requests\UpdateCourierRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourierController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status')->toString(),
        ];

        $couriers = User::query()
            ->couriers()
            ->withCount([
                'assignedOrders',
                'assignedOrders as open_orders_count' => fn (Builder $query) => $query
                    ->whereNotIn('status', [OrderStatus::Delivered->value, OrderStatus::Cancelled->value]),
            ])
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $search = $filters['search'];
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $courier): array => [
                'id' => $courier->id,
                'name' => $courier->name,
                'email' => $courier->email,
                'is_active' => $courier->is_active,
                'orders_count' => $courier->assigned_orders_count,
                'open_orders_count' => (int) $courier->getAttribute('open_orders_count'),
            ]);

        return Inertia::render('couriers/Index', [
            'couriers' => $couriers,
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('couriers/Create');
    }

    public function store(StoreCourierRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $courier = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => UserRole::Courier,
            'is_active' => $data['is_active'] ?? true,
        ]);

        // Accounts created by the admin are trusted: there is no inbox to confirm and
        // email_verified_at is not mass assignable.
        $courier->forceFill(['email_verified_at' => now()])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Courier created.')]);

        return redirect()->route('couriers.index');
    }

    public function edit(User $courier): Response
    {
        return Inertia::render('couriers/Edit', [
            'courier' => [
                'id' => $courier->id,
                'name' => $courier->name,
                'email' => $courier->email,
                'is_active' => $courier->is_active,
                'open_orders_count' => $this->openOrdersCount($courier),
            ],
        ]);
    }

    public function update(UpdateCourierRequest $request, User $courier): RedirectResponse
    {
        $data = $request->validated();

        $courier->fill([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        if (filled($data['password'] ?? null)) {
            $courier->password = $data['password'];
        }

        $courier->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Courier updated.')]);

        return redirect()->route('couriers.index');
    }

    /**
     * Deactivate a courier: they can no longer sign in nor receive new orders, while
     * their history stays intact for the reports. Couriers are never deleted because
     * orders.courier_id is nulled on delete, which would silently orphan past orders
     * and break the per-courier reporting.
     */
    public function deactivate(User $courier): RedirectResponse
    {
        $courier->update(['is_active' => false]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Courier deactivated.')]);

        return back();
    }

    public function activate(User $courier): RedirectResponse
    {
        $courier->update(['is_active' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Courier activated.')]);

        return back();
    }

    /**
     * Orders the courier is still carrying, so the admin knows what needs reassigning
     * before deactivating them.
     */
    private function openOrdersCount(User $courier): int
    {
        return $courier->assignedOrders()
            ->whereNotIn('status', [OrderStatus::Delivered->value, OrderStatus::Cancelled->value])
            ->count();
    }
}
