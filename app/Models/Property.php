<?php

namespace App\Models;

use App\Enums\PropertyStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use SoftDeletes;

    protected $table = 'properties';

    protected $casts = [
        'id_user'             => 'integer',
        'prix_loyer'          => 'float',
        'nb_avance'           => 'integer',
        'caution_electricite' => 'float',
        'caution_eau'         => 'float',
        'nb_pieces'           => 'integer',
        'date_debut_louer'    => 'datetime',
        'is_verified'         => 'boolean',
        'eau_courante'        => 'boolean',
        'electricite'         => 'boolean',
        'gardien'             => 'boolean',
        'parking'             => 'boolean',
        'meuble'              => 'boolean',
        'deleted_by'          => 'integer',
        'created_by'          => 'integer',
        'updated_by'          => 'integer',
    ];

    protected $fillable = [
        'id_user', 'title', 'description', 'indications_acces',
        'quartier', 'commune', 'ville', 'pays',
        'prix_loyer', 'devise', 'type_logement', 'condition',
        'nb_avance', 'caution_electricite', 'caution_eau',
        'nb_pieces', 'status', 'date_debut_louer', 'is_verified',
        'eau_courante', 'electricite', 'gardien', 'parking', 'meuble',
        'deleted_by', 'created_by', 'updated_by',
    ];

    // ── Relations ────────────────────────────────────────────────────

    public function proprietaire()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function accessContacts()
    {
        return $this->hasMany(AccessContact::class, 'id_property');
    }

    // Tous les médias, triés par ordre
    public function medias()
    {
        return $this->hasMany(PropertyMedia::class, 'id_property')->orderBy('ordre');
    }

    // Images uniquement
    public function images()
    {
        return $this->hasMany(PropertyMedia::class, 'id_property')
            ->where('type', 'image')
            ->orderBy('ordre');
    }

    // Vidéos uniquement
    public function videos()
    {
        return $this->hasMany(PropertyMedia::class, 'id_property')
            ->where('type', 'video')
            ->orderBy('ordre');
    }

    // Média principal (image de couverture)
    public function mediaPrincipal()
    {
        return $this->hasOne(PropertyMedia::class, 'id_property')
            ->where('is_principale', true)
            ->where('type', 'image');
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeDisponible($query)
    {
        return $query->where('status', PropertyStatus::DISPONIBLE->value);
    }

    public function scopeVerifie($query)
    {
        return $query->where('is_verified', true);
    }
}