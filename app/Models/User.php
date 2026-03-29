<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Traits\HasRoles;


/**
 * Class User
 * 
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property Carbon|null $email_verified_at
 * @property string|null $avatar
 * @property string|null $preferences
 * @property string|null $adress
 * @property bool $is_suscribed
 * @property Carbon|null $subscription_start
 * @property Carbon|null $subscription_end
 * @property bool $is_verified
 * @property string $password
 * @property string|null $remember_token
 * @property string|null $deleted_at
 * @property int|null $deleted_by
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property User|null $user
 * @property Collection|Abonnement[] $abonnements
 * @property Collection|AccessContact[] $access_contacts
 * @property Collection|ImagesProperty[] $images_properties
 * @property Collection|Packaccess[] $packaccesses
 * @property Collection|Paiement[] $paiements
 * @property Collection|Property[] $properties
 * @property Collection|Signalement[] $signalements
 * @property Collection|SupportsTicket[] $supports_tickets
 * @property Collection|User[] $users
 *
 * @package App\Models
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasRoles, SoftDeletes, HasApiTokens, HasFactory, Notifiable;

    protected string $guard_name = 'api';

    protected $table = 'users';

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_suscribed'      => 'boolean',
        'subscription_start' => 'datetime',
        'subscription_end'   => 'datetime',
        'is_verified'       => 'boolean',
        'deleted_by'        => 'integer',
        'created_by'        => 'integer',
        'updated_by'        => 'integer',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'avatar',
        'preferences',
        'adress',
        'is_suscribed',
        'subscription_start',
        'subscription_end',
        'is_verified',
        'email_verified_at',
        'remember_token',
        'deleted_by',
        'created_by',
        'updated_by',
    ];


    // ─── Relations métier (préparation Phase 3+) ──────────────────────
    public function properties()
    {
        return $this->hasMany(Property::class, 'user_id');
    }

    public function paiements()
    {
        return $this->hasMany(Paiement::class, 'user_id');
    }

    public function abonnements()
    {
        return $this->hasMany(Abonnement::class, 'user_id');
    }

    public function accessContacts()
    {
        return $this->hasMany(AccessContact::class, 'user_id');
    }

    public function signalements()
    {
        return $this->hasMany(Signalement::class, 'user_id');
    }

    public function supportsTickets()
    {
        return $this->hasMany(SupportsTicket::class, 'user_id');
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
