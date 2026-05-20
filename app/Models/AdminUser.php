<?php

declare(strict_types=1);

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AdminUser extends Model
{
	protected $table =  'admin_user';

    protected $fillable = [
		'name',
		'peer_id',
    ];
}
