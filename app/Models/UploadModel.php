<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UploadModel extends Model
{
    //
    protected $table = 't_uploads';

    protected $fillable = ['file_path', 'type', 'size', 'alt_text'];
}
