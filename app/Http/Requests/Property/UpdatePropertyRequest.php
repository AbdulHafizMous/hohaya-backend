<?php

namespace App\Http\Requests\Property;

use App\Enums\PropertyType;
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
            'indications_acces'   => ['sometimes', 'nullable', 'string', 'max:1000'],
            'lien_maps'           => ['sometimes', 'nullable', 'url', 'max:500'],
            'quartier'            => ['sometimes', 'string', 'max:100'],
            'ville'               => ['sometimes', 'string', 'max:100'],
            'prix_loyer'          => ['sometimes', 'numeric', 'min:5000'],
            'type_logement'       => ['sometimes', PropertyType::rule()],
            'condition'           => ['sometimes', 'string', 'max:255'],
            'nb_avance'           => ['sometimes', 'integer', 'min:1', 'max:12'],
            'caution_mois'        => ['sometimes', 'nullable', 'integer', 'min:0'],
            'nb_pieces'           => ['sometimes', 'integer', 'min:1'],
            'date_debut_louer'    => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'prix_loyer.numeric'   => 'Le prix du loyer doit être un nombre.',
            'type_logement.in'     => 'Type invalide. Valeurs : ' . implode(', ', PropertyType::values()),
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