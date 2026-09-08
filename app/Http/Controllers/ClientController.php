<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
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
}
