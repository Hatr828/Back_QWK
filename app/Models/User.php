<?php
namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Support\Facades\Hash;

class User extends Model implements JWTSubject, AuthenticatableContract
{
    use Authenticatable;

    protected $table = 'users';    
    protected $primaryKey = 'id';   

    protected $fillable = ['name','email','password','role'];
    protected $hidden   = ['password'];

    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = Hash::needsRehash($value) ? Hash::make($value) : $value;
    }

    // JWT
    public function getJWTIdentifier(){ return $this->getKey(); }
    public function getJWTCustomClaims(): array
    {
        return [
            'name'  => $this->name,
            'email' => $this->email,
            'role'  => $this->role,
        ];
    }
}
