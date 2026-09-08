<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Address;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->toString(),
            'has_orders' => $request->string('has_orders')->toString(),
            'sort' => $request->string('sort')->toString(),
            'status' => $request->string('status')->toString(),
        ];

        $clients = Client::query()
            ->withCount('orders')
            ->with('addresses:id,client_id,label,street,neighborhood,city')
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] === 'archived',
                fn ($query) => $query->where('is_active', false),
                // Archived clients are out of the way by default but never gone.
                fn ($query) => $query->when($filters['status'] !== 'all', fn ($inner) => $inner->where('is_active', true)),
            )
            ->when($filters['has_orders'] === 'with', fn ($query) => $query->has('orders'))
            ->when($filters['has_orders'] === 'without', fn ($query) => $query->doesntHave('orders'))
            ->when(
                $filters['sort'] === 'orders',
                fn ($query) => $query->orderByDesc('orders_count'),
                fn ($query) => $query->orderBy('name'),
            )
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Client $client): array => [
                'id' => $client->id,
                'name' => $client->name,
                'phone' => $client->phone,
                'orders_count' => $client->orders_count,
                'is_active' => $client->is_active,
                'address' => $client->addresses->first()?->street,
            ]);

        return Inertia::render('clients/Index', [
            'clients' => $clients,
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('clients/Create');
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data): void {
            $client = Client::create([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $client->addresses()->create([
                'label' => $data['address']['label'] ?? null,
                'street' => $data['address']['street'],
                'neighborhood' => $data['address']['neighborhood'] ?? null,
                'city' => $data['address']['city'] ?? null,
                'landmark' => $data['address']['landmark'] ?? null,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client created.')]);

        return redirect()->route('clients.index');
    }

    public function edit(Client $client): Response
    {
        $client->load('addresses');

        return Inertia::render('clients/Edit', [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'phone' => $client->phone,
                'notes' => $client->notes,
                'is_active' => $client->is_active,
                'orders_count' => $client->orders()->count(),
                'addresses' => $client->addresses
                    ->map(fn (Address $address): array => [
                        'id' => $address->id,
                        'label' => $address->label,
                        'street' => $address->street,
                        'neighborhood' => $address->neighborhood,
                        'city' => $address->city,
                        'landmark' => $address->landmark,
                        // An address already used by an order cannot be removed: orders
                        // point at it and the history has to keep resolving.
                        'in_use' => $address->orders()->exists(),
                    ])
                    ->values(),
            ],
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $client): void {
            $client->update([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $kept = [];

            foreach ($data['addresses'] as $address) {
                $attributes = [
                    'label' => $address['label'] ?? null,
                    'street' => $address['street'],
                    'neighborhood' => $address['neighborhood'] ?? null,
                    'city' => $address['city'] ?? null,
                    'landmark' => $address['landmark'] ?? null,
                ];

                $id = $address['id'] ?? null;
                $existing = $id === null ? null : $client->addresses()->whereKey($id)->first();

                if ($existing instanceof Address) {
                    $existing->update($attributes);
                    $kept[] = $existing->id;

                    continue;
                }

                $kept[] = $client->addresses()->create($attributes)->id;
            }

            // Whatever the form dropped goes, unless an order still points at it.
            $client->addresses()
                ->whereNotIn('id', $kept)
                ->whereDoesntHave('orders')
                ->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client updated.')]);

        return redirect()->route('clients.index');
    }

    /**
     * A client with orders is never deleted: the orders, the reports and every past
     * cash cut are anchored to it. Those get archived instead, which takes them out
     * of the new-order picker while the history keeps resolving. Only a client that
     * never got used — a duplicate or a typo — is really removed.
     */
    public function destroy(Client $client): RedirectResponse
    {
        if ($client->orders()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('This client has orders, archive it instead of deleting it.'),
            ]);

            return back();
        }

        $client->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client deleted.')]);

        return redirect()->route('clients.index');
    }

    public function archive(Client $client): RedirectResponse
    {
        $client->update(['is_active' => false]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client archived.')]);

        return back();
    }

    public function restore(Client $client): RedirectResponse
    {
        $client->update(['is_active' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client restored.')]);

        return back();
    }
}
