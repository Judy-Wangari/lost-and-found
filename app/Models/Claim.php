<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Claim extends Model
{
    protected $fillable =[
    'claimed_by',
    'item_id',
    'lost_item_id',
    'brand_model_or_logo',
    'what_was_inside_or_attached',
    'hidden_or_internal_details',
    'extra_notes',
    'status',
    'pending_review_count',
    'clarification_request_text',
    ];


public function item()
{
    return $this->belongsTo(Item::class);
}

public function lostItem()
{
    return $this->belongsTo(LostItem::class);
}

public function claimedBy()
{
    return $this->belongsTo(User::class, 'claimed_by');
}

}
