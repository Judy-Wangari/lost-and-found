<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appeal extends Model
{
    protected $fillable =[
    'claim_id',
    'raised_by',
    'item_id',
    'status',
    'reason',
    ];

    public function claim()
    {
        return $this->belongsTo(Claim::class);
    }

    public function raisedBy()
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
