<?php

namespace App\Models; 

use App\Notifications\CustomVerifyEmailNotification;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract; 


class User extends Authenticatable implements JWTSubject,MustVerifyEmail,CanResetPasswordContract 
{
    use HasApiTokens, HasFactory, Notifiable,MustVerifyEmailTrait,CanResetPassword;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
   
    protected $fillable = [ 
        'email',
        'phone',
        'password', 
        'firstname', 
        'lastname',
        'google_id', 
        'avatar', 
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];


      public function addresses()
    {
        return $this->hasMany(\App\Models\Address::class);
    }



    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function getAvatarAttribute($value)
     {
         if (!$value) {
             return null;
         }
         // If Google user and already has full URL
         if ($this->google_id && filter_var($value, FILTER_VALIDATE_URL)) {
             return $value;
         }
         // For non-Google users or relative paths
         if (!$this->google_id || !filter_var($value, FILTER_VALIDATE_URL)) {
             return url($value);
         }
         return $value;
     }
     public function sendPasswordResetNotification($token)
     {
         $this->notify(new ResetPasswordNotification($token));
     }
     public function sendEmailVerificationNotification()
     {
         $this->notify(new CustomVerifyEmailNotification);
     }
     
      
}
