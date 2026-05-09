<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Model
{
    protected $fillable = [
        'name',
        'email',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function joinedEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_participants')
            ->withPivot('joined_at')
            ->withTimestamps();
    }
}
