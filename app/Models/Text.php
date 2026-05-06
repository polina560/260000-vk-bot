<?php

namespace App\Models;

use Database\Factories\TextFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MoonShine\ChangeLog\Traits\HasChangeLog;

class Text extends Model
{
    use HasChangeLog;

    /** @use HasFactory<TextFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
    ];
}
