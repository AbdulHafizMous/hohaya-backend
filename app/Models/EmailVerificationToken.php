<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailVerificationToken extends Model
{
    protected $table = 'email_verification_tokens';

    protected $fillable = ['id_user', 'token', 'expires_at', 'consumed_at'];

    protected $casts = [
        'id_user'     => 'integer',
        'expires_at'  => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }
}
