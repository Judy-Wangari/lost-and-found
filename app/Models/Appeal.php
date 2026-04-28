<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appeal extends Model
{
    protected $fillable = [
        'claim_id',
        'item_id',
        'raised_by',
        'reason',
        'status',
        'admin_note',
    ];

    public function claim()
    {
        return $this->belongsTo(Claim::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function raisedBy()
    {
        return $this->belongsTo(User::class, 'raised_by');
    }
}