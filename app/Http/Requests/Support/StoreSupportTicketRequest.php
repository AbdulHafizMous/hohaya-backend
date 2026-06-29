<?php

namespace App\Http\Requests\Support;

use App\Enums\TicketCategorie;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreSupportTicketRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'sujet'              => ['required', 'string', 'max:255'],
            'message'            => ['required', 'string', 'max:5000'],
            'categorie'          => ['required', TicketCategorie::rule()],
            'canal_preference'   => ['sometimes', 'in:email,whatsapp,appel'],
            'telephone_contact'  => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'sujet.required'     => 'Le sujet est obligatoire.',
            'message.required'   => 'Le message est obligatoire.',
            'categorie.required' => 'La catégorie est obligatoire.',
            'categorie.in'       => 'Catégorie invalide. Valeurs : ' . implode(', ', TicketCategorie::values()),
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