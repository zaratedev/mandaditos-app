<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCourierRequest extends FormRequest
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
        /** @var User $courier */
        $courier = $this->route('courier');

        return [
            ...$this->profileRules($courier->id),
            'password' => $this->optionalPasswordRules(),
        ];
    }
}
