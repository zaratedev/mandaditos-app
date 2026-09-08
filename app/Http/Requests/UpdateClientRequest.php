<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Client $client */
        $client = $this->route('client');

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
            'addresses' => ['required', 'array', 'min:1'],
            // An id means "update this one"; its absence means a new address. Either
            // way it has to belong to this client, so a crafted id cannot reach
            // somebody else's address.
            'addresses.*.id' => [
                'nullable',
                'integer',
                Rule::exists('addresses', 'id')->where('client_id', $client->id),
            ],
            'addresses.*.street' => ['required', 'string', 'max:255'],
            'addresses.*.label' => ['nullable', 'string', 'max:50'],
            'addresses.*.neighborhood' => ['nullable', 'string', 'max:255'],
            'addresses.*.city' => ['nullable', 'string', 'max:255'],
            'addresses.*.landmark' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'addresses.min' => 'El cliente necesita al menos una dirección.',
            'addresses.required' => 'El cliente necesita al menos una dirección.',
        ];
    }
}
