<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Http\Requests\StorePublicOrderRequest;
use App\Models\Address;
use App\Models\Business;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderPlaced;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public, self-service order portal (§18). The client fills the form under the
 * business's slug and the order lands as `requested`, `source = portal`, for the
 * admin to confirm, price, assign and charge exactly as a manual one.
 */
class PublicOrderController extends Controller
{
    public function create(Business $business): Response
    {
        return Inertia::render('public/OrderForm', [
            'business' => [
                'name' => $business->name,
                'slug' => $business->slug,
            ],
            'submitUrl' => route('public.orders.store', $business),
            'success' => session('success'),
        ]);
    }

    public function store(StorePublicOrderRequest $request, Business $business): RedirectResponse
    {
        $data = $request->validated();

        $order = DB::transaction(function () use ($data, $business): Order {
            // Reuse the client for this number within the business, so a repeat
            // customer builds one history instead of a new row each time. Nothing
            // about an existing client is ever revealed back to the browser.
            $client = Client::firstOrCreate(
                ['tenant_id' => $business->id, 'phone' => $data['phone']],
                ['name' => $data['customer_name']],
            );

            $address = Address::create([
                'tenant_id' => $business->id,
                'client_id' => $client->id,
                'street' => $data['street'],
                'neighborhood' => $data['neighborhood'] ?? null,
                'city' => $data['city'] ?? null,
                'landmark' => $data['landmark'] ?? null,
            ]);

            /** @var list<string> $rawItems */
            $rawItems = $data['items'];

            $items = array_values(array_filter(
                array_map(fn (string $item): string => trim($item), $rawItems),
                fn (string $item): bool => $item !== '',
            ));

            $order = Order::create([
                'tenant_id' => $business->id,
                'client_id' => $client->id,
                'address_id' => $address->id,
                'status' => OrderStatus::Requested,
                'source' => OrderSource::Portal,
                // Keep the raw list too, derived from the lines: it satisfies the
                // NOT NULL column and gives the admin a one-glance summary.
                'shopping_list' => implode("\n", $items),
                'payment_status' => PaymentStatus::Pending,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $order->items()->create(['name' => $item, 'quantity' => 1]);
            }

            return $order;
        });

        // Single-tenant pilot: notify every admin. Scoping to the order's tenant is
        // part of the multi-tenant hardening (§18, Fase 3c).
        Notification::send(
            User::where('role', UserRole::Admin->value)->get(),
            new OrderPlaced($order),
        );

        return redirect()
            ->route('public.orders.create', $business)
            ->with('success', ['orderId' => $order->id]);
    }
}
