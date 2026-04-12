<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable =[
        'user_id',
        'message_body',
        'is_read',
        'type',
        'reference_id',
        'reference_type',

    ];



    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
