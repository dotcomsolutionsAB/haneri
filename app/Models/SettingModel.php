<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettingModel extends Model
{
    //
    protected $table = 't_settings';

    protected $fillable = ['key', 'value'];
}
