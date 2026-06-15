<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Paiement extends Model
{
    use SoftDeletes;

    protected $table = 'paiements';

    protected $casts = [
        'id_user'        => 'integer',
        'id_abonnement'  => 'integer',
        'id_property'    => 'integer',
        'montant'        => 'float',
        'kkiapay_response' => 'array',
        'paye_le'        => 'datetime',
        'deleted_by'     => 'integer',
        'created_by'     => 'integer',
        'updated_by'     => 'integer',
    ];

    protected $fillable = [
        'id_user', 'type', 'status', 'montant', 'devise',
        'kkiapay_transaction_id', 'kkiapay_reference', 'kkiapay_response',
        'id_abonnement', 'id_property',
        'telephone_paiement', 'operateur',
        'raison_echec', 'paye_le',
        'deleted_by', 'created_by', 'updated_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function abonnement()
    {
        return $this->belongsTo(Abonnement::class, 'id_abonnement');
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'id_property');
    }
}