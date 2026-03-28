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
 * Class Property
 * 
 * @property int $id
 * @property int $id_user
 * @property string $title
 * @property string $description
 * @property string $quartier
 * @property string $ville
 * @property float $prix_loyer
 * @property string $type_logement
 * @property string $condition
 * @property int $nb_avance
 * @property float|null $caution_electricite
 * @property float|null $caution_eau
 * @property int $nb_pieces
 * @property string $status
 * @property Carbon|null $date_debut_louer
 * @property bool $is_verified
 * @property string|null $deleted_at
 * @property int|null $deleted_by
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property User|null $user
 * @property Collection|AccessContact[] $access_contacts
 * @property Collection|ImagesProperty[] $images_properties
 *
 * @package App\Models
 */
class Property extends Model
{
	use SoftDeletes;
	protected $table = 'properties';

	protected $casts = [
		'id_user' => 'int',
		'prix_loyer' => 'float',
		'nb_avance' => 'int',
		'caution_electricite' => 'float',
		'caution_eau' => 'float',
		'nb_pieces' => 'int',
		'date_debut_louer' => 'datetime',
		'is_verified' => 'bool',
		'deleted_by' => 'int',
		'created_by' => 'int',
		'updated_by' => 'int'
	];

	protected $fillable = [
		'id_user',
		'title',
		'description',
		'quartier',
		'ville',
		'prix_loyer',
		'type_logement',
		'condition',
		'nb_avance',
		'caution_electricite',
		'caution_eau',
		'nb_pieces',
		'status',
		'date_debut_louer',
		'is_verified',
		'deleted_by',
		'created_by',
		'updated_by'
	];

	public function proprietaire()
	{
		return $this->belongsTo(User::class, 'id_user');
	}

	public function access_contacts()
	{
		return $this->hasMany(AccessContact::class, 'id_property');
	}

	public function images_properties()
	{
		return $this->hasMany(ImagesProperty::class, 'id_property');
	}
}
