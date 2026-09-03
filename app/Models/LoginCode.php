<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginCode extends Model
{
    protected $table = 'login_codes';

    protected $fillable = ['id_user', 'code', 'type', 'tentatives', 'expires_at', 'consumed_at'];

    protected $casts = [
        'id_user'     => 'integer',
        'tentatives'  => 'integer',
        'expires_at'  => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }
}
