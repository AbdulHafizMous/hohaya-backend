<?php

namespace App\Models;

use App\Enums\TicketCategorie;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportTicket extends Model
{
    use SoftDeletes;

    protected $table = 'supports_tickets';

    protected $casts = [
        'id_user'    => 'integer',
        'repondu_par' => 'integer',
        'repondu_le' => 'datetime',
        'deleted_by' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    protected $fillable = [
        'id_user', 'sujet', 'message', 'status', 'categorie',
        'reponse_admin', 'repondu_par', 'repondu_le',
        'canal_preference', 'telephone_contact',
        'deleted_by', 'created_by', 'updated_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function adminRepondant()
    {
        return $this->belongsTo(User::class, 'repondu_par');
    }
}