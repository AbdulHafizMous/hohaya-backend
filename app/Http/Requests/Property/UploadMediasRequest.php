<?php

namespace App\Http\Requests\Property;

use App\Enums\MediaType;
use App\Enums\PropertyZone;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UploadMediasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prépare les données avant la validation.
     * Utile pour convertir les chaînes "true"/"false" issues du multipart/form-data
     */
    protected function prepareForValidation()
    {
        if ($this->has('is_principale')) {
            $this->merge([
                'is_principale' => filter_var($this->is_principale, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $file = $this->file('fichier');
            $type = $this->input('type');

            if ($file && $type) {
                $mime = $file->getMimeType();

                if ($type === 'image' && !str_starts_with($mime, 'image/')) {
                    $validator->errors()->add('fichier', 'Le fichier doit être une image.');
                }

                if ($type === 'video' && !str_starts_with($mime, 'video/')) {
                    $validator->errors()->add('fichier', 'Le fichier doit être une vidéo.');
                }
            }
        });
    }

    public function rules(): array
    {
        return [
            'fichier'       => ['required', 'file', 'max:51200'], // 50MB
            'type'          => ['required', MediaType::rule()],
            'zone'          => ['required', PropertyZone::rule()],
            'description'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_principale' => ['sometimes', 'boolean', 'nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'fichier.required' => 'Le fichier est obligatoire.',
            'fichier.file'     => 'Le champ doit être un fichier valide.',
            'fichier.max'      => 'Le fichier ne doit pas dépasser 50 Mo.',

            'type.required'    => 'Le type (image/video) est obligatoire.',
            'type.in'          => 'Type invalide. Valeurs : ' . implode(', ', MediaType::values()),

            'zone.required'    => 'La zone est obligatoire.',
            'zone.in'          => 'Zone invalide. Valeurs : ' . implode(', ', PropertyZone::values()),

            'is_principale.boolean' => 'Le champ is_principale doit être true ou false.',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Données invalides.',
            'data'    => [
                'errors' => $validator->errors(),
            ],
        ], 422));
    }
}
