<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class ImagesProperty
 * 
 * @property int $id
 * @property int $id_property
 * @property string $url_image
 * @property string $chemin
 * @property bool $is_principale
 * @property int $ordre
 * @property string|null $deleted_at
 * @property int|null $deleted_by
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property User|null $user
 * @property Property $property
 *
 * @package App\Models
 */
class ImagesProperty extends Model
{
	use SoftDeletes;
	protected $table = 'images_properties';

	protected $casts = [
		'id_property' => 'int',
		'is_principale' => 'bool',
		'ordre' => 'int',
		'deleted_by' => 'int',
		'created_by' => 'int',
		'updated_by' => 'int'
	];

	protected $fillable = [
		'id_property',
		'url_image',
		'chemin',
		'is_principale',
		'ordre',
		'deleted_by',
		'created_by',
		'updated_by'
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	public function property()
	{
		return $this->belongsTo(Property::class, 'id_property');
	}
}
