<?php

namespace App\Http\Requests\Signalement;

use App\Enums\SignalementType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreSignalementRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'motif'              => ['required', 'string', 'max:255'],
            'description'        => ['required', 'string', 'max:3000'],
            'type_signalement'   => ['required', SignalementType::rule()],
            'id_property'        => ['sometimes', 'nullable', 'exists:properties,id'],
            'id_user_signale'    => ['sometimes', 'nullable', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'motif.required'            => 'Le motif est obligatoire.',
            'description.required'      => 'La description est obligatoire.',
            'type_signalement.required' => 'Le type est obligatoire.',
            'type_signalement.in'       => 'Type invalide. Valeurs : ' . implode(', ', SignalementType::values()),
            'id_property.exists'        => 'L\'annonce référencée est introuvable.',
            'id_user_signale.exists'    => 'L\'utilisateur référencé est introuvable.',
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