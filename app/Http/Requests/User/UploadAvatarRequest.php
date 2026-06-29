<?php

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UploadAvatarRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.required' => 'Veuillez sélectionner une image.',
            'avatar.image'    => 'Le fichier doit être une image.',
            'avatar.mimes'    => 'Formats acceptés : jpg, jpeg, png, webp.',
            'avatar.max'      => 'L\'image ne doit pas dépasser 2 Mo.',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Fichier invalide.',
            'data'    => ['errors' => $validator->errors()],
        ], 422));
    }
}