<?php

namespace App\Models;

use App\Enums\AbonnementStatus;
use App\Enums\AbonnementType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Abonnement extends Model
{
    use SoftDeletes;

    protected $table = 'abonnements';

    protected $casts = [
        'id_user'      => 'integer',
        'id_paiement'  => 'integer',
        'montant'      => 'float',
        'auto_renew'   => 'boolean',
        'date_debut'   => 'datetime',
        'date_fin'     => 'datetime',
        'deleted_by'   => 'integer',
        'created_by'   => 'integer',
        'updated_by'   => 'integer',
    ];

    protected $fillable = [
        'id_user', 'type', 'status', 'montant', 'devise',
        'date_debut', 'date_fin', 'auto_renew', 'id_paiement', 'note',
        'deleted_by', 'created_by', 'updated_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function paiement()
    {
        return $this->belongsTo(Paiement::class, 'id_paiement');
    }

    public function isActif(): bool
    {
        return $this->status === AbonnementStatus::ACTIF->value
            && $this->date_fin
            && $this->date_fin->isFuture();
    }

    public function joursRestants(): int
    {
        if (!$this->date_fin || $this->date_fin->isPast()) return 0;
        return (int) now()->diffInDays($this->date_fin);
    }
}