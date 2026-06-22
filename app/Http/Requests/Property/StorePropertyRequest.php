<?php

namespace App\Http\Requests\Property;

use App\Enums\PropertyType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StorePropertyRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title'               => ['required', 'string', 'max:200'],
            'description'         => ['required', 'string', 'max:5000'],
            'indications_acces'   => ['sometimes', 'nullable', 'string', 'max:1000'],
            'quartier'            => ['required', 'string', 'max:100'],
            'commune'             => ['sometimes', 'nullable', 'string', 'max:100'],
            'ville'               => ['required', 'string', 'max:100'],
            'pays'                => ['sometimes', 'string', 'max:100'],
            'prix_loyer'          => ['required', 'numeric', 'min:0'],
            'devise'              => ['sometimes', 'string', 'max:10'],
            'type_logement'       => ['required', PropertyType::rule()],
            'condition'           => ['required', 'string', 'max:255'],
            'nb_avance'           => ['sometimes', 'integer', 'min:1', 'max:12'],
            'caution_electricite' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'caution_eau'         => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'nb_pieces'           => ['required', 'integer', 'min:1'],
            'date_debut_louer'    => ['sometimes', 'nullable', 'date'],
            'eau_courante'        => ['sometimes', 'boolean'],
            'electricite'         => ['sometimes', 'boolean'],
            'gardien'             => ['sometimes', 'boolean'],
            'parking'             => ['sometimes', 'boolean'],
            'meuble'              => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'         => 'Le titre est obligatoire.',
            'description.required'   => 'La description est obligatoire.',
            'quartier.required'      => 'Le quartier est obligatoire.',
            'ville.required'         => 'La ville est obligatoire.',
            'prix_loyer.required'    => 'Le prix du loyer est obligatoire.',
            'prix_loyer.numeric'     => 'Le prix doit être un nombre.',
            'type_logement.required' => 'Le type de logement est obligatoire.',
            'type_logement.in'       => 'Type invalide. Valeurs : ' . implode(', ', \App\Enums\PropertyType::values()),
            'nb_pieces.required'     => 'Le nombre de pièces est obligatoire.',
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