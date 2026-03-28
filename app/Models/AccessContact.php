<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class AccessContact
 * 
 * @property int $id
 * @property int|null $id_user
 * @property int|null $id_property
 * @property int|null $id_paiement
 * @property string|null $deleted_at
 * @property int|null $deleted_by
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property User|null $user
 * @property Paiement|null $paiement
 * @property Property|null $property
 *
 * @package App\Models
 */
class AccessContact extends Model
{
	use SoftDeletes;
	protected $table = 'access_contacts';

	protected $casts = [
		'id_user' => 'int',
		'id_property' => 'int',
		'id_paiement' => 'int',
		'deleted_by' => 'int',
		'created_by' => 'int',
		'updated_by' => 'int'
	];

	protected $fillable = [
		'id_user',
		'id_property',
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

	public function property()
	{
		return $this->belongsTo(Property::class, 'id_property');
	}
}
