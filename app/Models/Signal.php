<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Signal extends Model
{
    protected $fillable = ['assessment_id', 'listing_id', 'code', 'status', 'value', 'points'];
}
