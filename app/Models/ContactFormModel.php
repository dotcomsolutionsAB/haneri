<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactFormModel extends Model
{
    use HasFactory;

    protected $table = 't_contact_form';

    protected $fillable = [
        'name',
        'email',
        'mobile',
        'comments',
    ];
}