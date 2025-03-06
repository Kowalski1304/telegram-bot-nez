<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'source',
        'category',
        'description',
    ];
    public $timestamps = false;

}
