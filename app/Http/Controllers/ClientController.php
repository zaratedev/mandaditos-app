<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('clients/Index', [
            'clients' => Client::query()
                ->withCount('orders')
                ->with('addresses:id,client_id,label,street,neighborhood,city')
                ->orderBy('name')
                ->get(['id', 'name', 'phone', 'notes']),
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
