<?php

namespace App\Http\Requests\Property;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdatePropertyRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title'               => ['sometimes', 'string', 'max:200'],
            'description'         => ['sometimes', 'string', 'max:5000'],
            'quartier'            => ['sometimes', 'string', 'max:100'],
            'ville'               => ['sometimes', 'string', 'max:100'],
            'prix_loyer'          => ['sometimes', 'numeric', 'min:0'],
            'type_logement'       => ['sometimes', 'in:appartement,maison,studio'],
            'condition'           => ['sometimes', 'string', 'max:255'],
            'nb_avance'           => ['sometimes', 'integer', 'min:1', 'max:12'],
            'caution_electricite' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'caution_eau'         => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'nb_pieces'           => ['sometimes', 'integer', 'min:1'],
            'date_debut_louer'    => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'prix_loyer.numeric'   => 'Le prix du loyer doit être un nombre.',
            'type_logement.in'     => 'Type invalide. Valeurs : appartement, maison, studio.',
            'nb_pieces.min'        => 'Le nombre de pièces doit être au moins 1.',
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