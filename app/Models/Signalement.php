<?php

namespace App\Models;

use App\Enums\SignalementStatus;
use App\Enums\SignalementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Signalement extends Model
{
    use SoftDeletes;

    protected $table = 'signalements';

    protected $casts = [
        'id_user'          => 'integer',
        'id_property'      => 'integer',
        'id_user_signale'  => 'integer',
        'traite_par'       => 'integer',
        'traite_le'        => 'datetime',
        'deleted_by'       => 'integer',
        'created_by'       => 'integer',
        'updated_by'       => 'integer',
    ];

    protected $fillable = [
        'id_user', 'id_property', 'id_user_signale',
        'motif', 'description', 'type_signalement', 'status',
        'note_admin', 'traite_par', 'traite_le',
        'deleted_by', 'created_by', 'updated_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'id_property');
    }

    public function userSignale()
    {
        return $this->belongsTo(User::class, 'id_user_signale');
    }

    public function adminTraitant()
    {
        return $this->belongsTo(User::class, 'traite_par');
    }
}