<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Observation extends Model
{
    protected $fillable = ['listing_id', 'field', 'value', 'observed_by'];
}
