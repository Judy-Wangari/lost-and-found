<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable =[
    'claim_id',
    'item_id',
    'sender_id',
    'receiver_id',
    'message_body',
    'is_read',
    ];




public function claim()
{
    return $this->belongsTo(Claim::class);
}

public function sender()
{
    return $this->belongsTo(User::class, 'sender_id');
}

public function receiver()
{
    return $this->belongsTo(User::class, 'receiver_id');
}

}