<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:100'],
            'email'    => ['required_without:phone', 'nullable', 'email', 'unique:users,email', 'max:150'],
            'phone'    => ['required_without:email', 'nullable', 'string', 'unique:users,phone', 'max:20'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'role'     => ['required', 'in:owner,seeker'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'              => 'Le nom est obligatoire.',
            'name.max'                   => 'Le nom ne peut pas dépasser 100 caractères.',
            'email.required_without'     => 'Un email est requis si vous ne fournissez pas de téléphone.',
            'email.email'                => 'L\'adresse email n\'est pas valide.',
            'email.unique'               => 'Cette adresse email est déjà utilisée par un autre compte.',
            'phone.required_without'     => 'Un téléphone est requis si vous ne fournissez pas d\'email.',
            'phone.unique'               => 'Ce numéro de téléphone est déjà utilisé par un autre compte.',
            'password.required'          => 'Le mot de passe est obligatoire.',
            'password.min'               => 'Le mot de passe doit contenir au moins 6 caractères.',
            'password.confirmed'         => 'Les mots de passe ne correspondent pas.',
            'role.required'              => 'Le rôle est obligatoire.',
            'role.in'                    => 'Le rôle doit être "owner" ou "seeker".',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Données invalides. Veuillez corriger les erreurs.',
            'data'    => ['errors' => $validator->errors()],
        ], 422));
    }
}