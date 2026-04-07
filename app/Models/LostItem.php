<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LostItem extends Model
{
  protected $fillable = [
    'posted_by',
    'category',
    'general_description',
    'photo_path',
    'status',
   ];
}
