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


    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_STAFF = 'staff';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';

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
        'role',
        'status',
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
        'role' => 'string',
    ];

     /**
     * Get the available role values.
     *
     * @return array
     */
    public static function getAvailableRoles()
    {
        return [
            self::ROLE_CUSTOMER,
            self::ROLE_ADMIN,
            self::ROLE_STAFF,
        ];
    }

    /**
     * Set the role attribute.
     *
     * @param  string  $value
     * @return void
     */
    public function setRoleAttribute($value)
    {
        if (!in_array($value, self::getAvailableRoles())) {
            throw new \InvalidArgumentException("Invalid role");
        }
        $this->attributes['role'] = $value;
    }

    public static function getAvailableStatuses()
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_SUSPENDED,
        ];
    }
    
    public function setStatusAttribute($value)
    {
        if (!in_array($value, self::getAvailableStatuses())) {
            throw new \InvalidArgumentException("Invalid status");
        }
        $this->attributes['status'] = $value;
    }


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
    public function getFullnameAttribute()
    {
        return "{$this->firstname} {$this->lastname}";
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
     
    public function orders()
    {
        return $this->hasMany(\App\Models\Order::class);
    }
      
}
