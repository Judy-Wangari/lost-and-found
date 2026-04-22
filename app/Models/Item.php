<?php

namespace App\Models;
use App\Models\ItemPrivateDetail;
use App\Models\User;
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

public function postedBy()
{
    return $this->belongsTo(User::class, 'posted_by');
}

public function claimedByUser()
{
    return $this->belongsTo(User::class, 'claimed_by');
}
public function itemPrivateDetail()
{
    return $this->hasOne(ItemPrivateDetail::class);
}
public function poster()
{
    return $this->belongsTo(User::class, 'posted_by');
}
}
