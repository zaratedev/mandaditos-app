<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreCourierRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

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
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'is_active' => ['boolean'],
        ];
    }
}
