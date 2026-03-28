<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Paiement
 * 
 * @property int $id
 * @property int $id_user
 * @property string $type_paiement
 * @property float $montant
 * @property string $reference_paiement
 * @property string $methode_paiement
 * @property string $status
 * @property string|null $numero_mobile
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
 * @property Collection|Packaccess[] $packaccesses
 *
 * @package App\Models
 */
class Paiement extends Model
{
	use SoftDeletes;
	protected $table = 'paiements';

	protected $casts = [
		'id_user' => 'int',
		'montant' => 'float',
		'deleted_by' => 'int',
		'created_by' => 'int',
		'updated_by' => 'int'
	];

	protected $fillable = [
		'id_user',
		'type_paiement',
		'montant',
		'reference_paiement',
		'methode_paiement',
		'status',
		'numero_mobile',
		'deleted_by',
		'created_by',
		'updated_by'
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'id_user');
	}

	public function abonnements()
	{
		return $this->hasMany(Abonnement::class, 'id_paiement');
	}

	public function access_contacts()
	{
		return $this->hasMany(AccessContact::class, 'id_paiement');
	}

	public function packaccesses()
	{
		return $this->hasMany(Packaccess::class, 'id_paiement');
	}
}
