<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Packaccess
 * 
 * @property int $id
 * @property int $id_user
 * @property int $nb_access_total
 * @property int $nb_access_utilises
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
class Packaccess extends Model
{
	use SoftDeletes;
	protected $table = 'packaccess';

	protected $casts = [
		'id_user' => 'int',
		'nb_access_total' => 'int',
		'nb_access_utilises' => 'int',
		'id_paiement' => 'int',
		'deleted_by' => 'int',
		'created_by' => 'int',
		'updated_by' => 'int'
	];

	protected $fillable = [
		'id_user',
		'nb_access_total',
		'nb_access_utilises',
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
