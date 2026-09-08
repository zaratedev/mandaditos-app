<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
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
        /** @var Order $order */
        $order = $this->route('order');

        return [
            'address_id' => [
                'required',
                'integer',
                Rule::exists('addresses', 'id')->where('client_id', $order->client_id),
            ],
            'courier_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', UserRole::Courier->value)
                    ->where(fn ($inner) => $inner
                        ->where('is_active', true)
                        ->when(
                            $order->courier_id !== null,
                            fn ($q) => $q->orWhere('id', $order->courier_id),
                        ))),
            ],
            'shopping_list' => ['required', 'string'],
            'commission' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.name' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'address_id.exists' => 'La dirección seleccionada no pertenece al cliente.',
        ];
    }
}
