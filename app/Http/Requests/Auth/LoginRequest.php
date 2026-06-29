<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'email'    => ['required_without:phone', 'nullable', 'email'],
            'phone'    => ['required_without:email', 'nullable', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required_without' => 'Un email est requis si vous ne fournissez pas de téléphone.',
            'email.email'            => 'L\'adresse email n\'est pas valide.',
            'phone.required_without' => 'Un téléphone est requis si vous ne fournissez pas d\'email.',
            'password.required'      => 'Le mot de passe est obligatoire.',
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