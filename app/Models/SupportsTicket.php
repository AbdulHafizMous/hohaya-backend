<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class SupportsTicket
 * 
 * @property int $id
 * @property int $id_user
 * @property string $sujet
 * @property string $message
 * @property string $status
 * @property string $categorie
 * @property string|null $deleted_at
 * @property int|null $deleted_by
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property User|null $user
 *
 * @package App\Models
 */
class SupportsTicket extends Model
{
	use SoftDeletes;
	protected $table = 'supports_tickets';

	protected $casts = [
		'id_user' => 'int',
		'deleted_by' => 'int',
		'created_by' => 'int',
		'updated_by' => 'int'
	];

	protected $fillable = [
		'id_user',
		'sujet',
		'message',
		'status',
		'categorie',
		'deleted_by',
		'created_by',
		'updated_by'
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'id_user');
	}
}
