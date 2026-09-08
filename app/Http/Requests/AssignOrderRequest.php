<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignOrderRequest extends FormRequest
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
        return [
            'courier_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', UserRole::Courier->value)
                    ->where('is_active', true)),
            ],
        ];
    }
}
