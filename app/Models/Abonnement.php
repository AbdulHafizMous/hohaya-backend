<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Abonnement
 * 
 * @property int $id
 * @property int $id_user
 * @property string $type_abonnement
 * @property Carbon $date_debut
 * @property Carbon $date_fin
 * @property string $status
 * @property int $id_paiement
 * @property string|null $deleted_at
 * @property int|null $deleted_by
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property User|null $user
 * @property Paiement $paiement
 *
 * @package App\Models
 */
class Abonnement extends Model
{
	use SoftDeletes;
	protected $table = 'abonnements';

	protected $casts = [
		'id_user' => 'int',
		'date_debut' => 'datetime',
		'date_fin' => 'datetime',
		'id_paiement' => 'int',
		'deleted_by' => 'int',
		'created_by' => 'int',
		'updated_by' => 'int'
	];

	protected $fillable = [
		'id_user',
		'type_abonnement',
		'date_debut',
		'date_fin',
		'status',
		'id_paiement',
		'deleted_by',
		'created_by',
		'updated_by'
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'id_user');
	}

	public function paiement()
	{
		return $this->belongsTo(Paiement::class, 'id_paiement');
	}
}
