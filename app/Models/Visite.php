<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Visite extends Model
{
    use SoftDeletes;

    protected $table = 'visites';

    protected $casts = [
        'id_user'        => 'integer',
        'id_property'    => 'integer',
        'date_souhaitee' => 'datetime',
    ];

    protected $fillable = [
        'id_user', 'id_property', 'date_souhaitee',
        'message', 'status', 'note_proprietaire',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'id_property');
    }
}
