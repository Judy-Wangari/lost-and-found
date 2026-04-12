<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityHandover extends Model
{
    protected $fillable =[
    'item_id',
    'claim_id',
    'handed_over_by',
    'receiver_id',
    'owner_id',
    'status',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function claim()
    {
        return $this->belongsTo(Claim::class);
    }

    public function handedOverBy()
    {
        return $this->belongsTo(User::class, 'handed_over_by');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}

