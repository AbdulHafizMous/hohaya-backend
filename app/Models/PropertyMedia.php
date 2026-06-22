<?php

namespace App\Models;

use App\Enums\MediaType;
use App\Enums\PropertyZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PropertyMedia extends Model
{
    use SoftDeletes;

    protected $table = 'property_medias';

    protected $casts = [
        'id_property'     => 'integer',
        'is_principale'   => 'boolean',
        'ordre'           => 'integer',
        'taille'          => 'integer',
        'duree_secondes'  => 'integer',
        'deleted_by'      => 'integer',
        'created_by'      => 'integer',
        'updated_by'      => 'integer',
    ];

    protected $fillable = [
        'id_property', 'type', 'zone',
        'url', 'chemin', 'nom_original', 'taille', 'mime_type',
        'duree_secondes', 'is_principale', 'ordre', 'description',
        'deleted_by', 'created_by', 'updated_by',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class, 'id_property');
    }

    public function isImage(): bool
    {
        return $this->type === MediaType::IMAGE->value;
    }

    public function isVideo(): bool
    {
        return $this->type === MediaType::VIDEO->value;
    }
}