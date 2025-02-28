<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable; // Add this line

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone_number', 'state', 'country', 'birthday', 'password', 'role', 'blocked', 'email_verified_at'
    ];

    protected $hidden = ['password'];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'role' => $this->role,
            'blocked' => $this->blocked,
        ];
    }
}