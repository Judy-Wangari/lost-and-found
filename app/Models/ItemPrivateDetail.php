<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemPrivateDetail extends Model
{
    protected $fillable = [
    'item_id',
    'brand_model_or_logo',
    'what_was_inside_or_attached',
    'hidden_or_internal_details',
    'extra_notes',
    'location_found'
    ];
}
