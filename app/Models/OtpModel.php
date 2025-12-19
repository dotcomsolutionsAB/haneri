<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtpModel extends Model
{
    use HasFactory;

    protected $table = 't_mobile_otp';

    protected $fillable = [
        'mobile',
        'otp',
        'status',
    ];
}
