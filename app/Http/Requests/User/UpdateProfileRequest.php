<?php

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'name'        => ['sometimes', 'string', 'max:100'],
            'email'       => ['sometimes', 'nullable', 'email', Rule::unique('users', 'email')->ignore($userId), 'max:150'],
            'phone'       => ['sometimes', 'nullable', 'string', Rule::unique('users', 'phone')->ignore($userId), 'max:20'],
            'adress'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'preferences' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max'      => 'Le nom ne peut pas dépasser 100 caractères.',
            'email.email'   => 'L\'adresse email n\'est pas valide.',
            'email.unique'  => 'Cette adresse email est déjà utilisée par un autre compte.',
            'phone.unique'  => 'Ce numéro de téléphone est déjà utilisé par un autre compte.',
            'adress.max'    => 'L\'adresse ne peut pas dépasser 255 caractères.',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Données invalides.',
            'data'    => ['errors' => $validator->errors()],
        ], 422));
    }
}