<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
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
class User extends Authenticatable
{
	use HasRoles, SoftDeletes, HasApiTokens, HasFactory, Notifiable;
	protected $table = 'users';

	protected $casts = [
		'email_verified_at' => 'datetime',
		'is_suscribed' => 'bool',
		'subscription_start' => 'datetime',
		'subscription_end' => 'datetime',
		'is_verified' => 'bool',
		'deleted_by' => 'int',
		'created_by' => 'int',
		'updated_by' => 'int'
	];

	protected $hidden = [
		'password',
		'remember_token'
	];

	protected $fillable = [
		'name',
		'email',
		'phone',
		'email_verified_at',
		'avatar',
		'preferences',
		'adress',
		'is_suscribed',
		'subscription_start',
		'subscription_end',
		'is_verified',
		'password',
		'remember_token',
		'deleted_by',
		'created_by',
		'updated_by'
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'updated_by');
	}

	public function abonnements()
	{
		return $this->hasMany(Abonnement::class, 'updated_by');
	}

	public function access_contacts()
	{
		return $this->hasMany(AccessContact::class, 'updated_by');
	}

	public function images_properties()
	{
		return $this->hasMany(ImagesProperty::class, 'updated_by');
	}

	public function packaccesses()
	{
		return $this->hasMany(Packaccess::class, 'updated_by');
	}

	public function paiements()
	{
		return $this->hasMany(Paiement::class, 'updated_by');
	}

	public function properties()
	{
		return $this->hasMany(Property::class, 'updated_by');
	}

	public function signalements()
	{
		return $this->hasMany(Signalement::class, 'updated_by');
	}

	public function supports_tickets()
	{
		return $this->hasMany(SupportsTicket::class, 'updated_by');
	}

	public function users()
	{
		return $this->hasMany(User::class, 'updated_by');
	}
}
