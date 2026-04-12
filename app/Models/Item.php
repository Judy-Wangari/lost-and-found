<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
   protected $fillable = [
    'posted_by',
    'category',
    'photo_path',
    'status',
    'claimed_by',
    'verification_code'
   ];


   public function privateDetails()
{
    return $this->hasOne(ItemPrivateDetail::class);
}
}
