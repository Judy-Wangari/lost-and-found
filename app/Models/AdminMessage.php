<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminMessage extends Model
{
     protected $fillable =[
    'claim_id',
    'appeal_id',
    'sender_id',
    'receiver_id',
    'message',
    'type',
    'is_read',
    ];



    public function claim()
    {
        return $this->belongsTo(Claim::class);
    }

    public function appeal()
    {
        return $this->belongsTo(Appeal::class);
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
