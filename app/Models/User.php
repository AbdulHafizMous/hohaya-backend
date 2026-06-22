<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasRoles, SoftDeletes, HasApiTokens, HasFactory, Notifiable;

    protected string $guard_name = 'api';
    protected $table = 'users';

    protected $casts = [
        'email_verified_at'  => 'datetime',
        'is_suscribed'       => 'boolean',
        'subscription_start' => 'datetime',
        'subscription_end'   => 'datetime',
        'is_verified'        => 'boolean',
        'deleted_by'         => 'integer',
        'created_by'         => 'integer',
        'updated_by'         => 'integer',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $fillable = [
        'name', 'email', 'phone', 'password',
        'avatar', 'preferences', 'adress',
        'is_suscribed', 'subscription_start', 'subscription_end',
        'is_verified', 'email_verified_at', 'remember_token',
        'deleted_by', 'created_by', 'updated_by',
    ];

    // ─── Relations ────────────────────────────────────────────────────

    public function properties()
    {
        return $this->hasMany(Property::class, 'id_user');
    }

    public function paiements()
    {
        return $this->hasMany(Paiement::class, 'id_user');
    }

    public function abonnements()
    {
        return $this->hasMany(Abonnement::class, 'id_user');
    }

    public function accessContacts()
    {
        return $this->hasMany(AccessContact::class, 'id_user');
    }

    public function favoris()
    {
        return $this->hasMany(Favori::class, 'id_user');
    }

    public function visites()
    {
        return $this->hasMany(Visite::class, 'id_user');
    }

    public function signalements()
    {
        return $this->hasMany(Signalement::class, 'id_user');
    }

    public function supportsTickets()
    {
        return $this->hasMany(SupportTicket::class, 'id_user');
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    public function isOwner(): bool
    {
        return $this->hasRole('owner');
    }

    public function isSeeker(): bool
    {
        return $this->hasRole('seeker');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function hasActiveSubscription(): bool
    {
        return $this->is_suscribed
            && $this->subscription_end
            && $this->subscription_end->isFuture();
    }
}
