<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessContact extends Model
{
    protected $table = 'access_contacts';

    protected $casts = [
        'id_user'     => 'integer',
        'id_property' => 'integer',
        'id_paiement' => 'integer',
        'expire_le'   => 'datetime',
    ];

    protected $fillable = [
        'id_user', 'id_property', 'id_paiement', 'expire_le',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'id_property');
    }

    public function paiement()
    {
        return $this->belongsTo(Paiement::class, 'id_paiement');
    }
}