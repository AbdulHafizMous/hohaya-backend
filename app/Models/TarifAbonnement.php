<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TarifAbonnement extends Model
{
    protected $table = 'tarifs_abonnements';

    protected $casts = [
        'montant'  => 'float',
        'is_actif' => 'boolean',
    ];

    protected $fillable = [
        'type', 'montant', 'devise', 'is_actif', 'description',
    ];
}