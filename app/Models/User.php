<?php

namespace App\Models;

use App\Models\Core\Contact;
use App\Models\Core\PartyUserAccess;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Inventory\Warehouse;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

 
    public function contact(){ return $this->belongsTo(Contact::class); }

    public function partyUserAccesses(){ return $this->hasMany(PartyUserAccess::class); }

    /**
     * accessor avatar user
     */
    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value != null ? asset('/storage/avatars/' . $value) : asset('avatar.png'),
        );
    }



    public function warehouses()
    {
        return $this->belongsToMany(Warehouse::class, 'user_warehouses')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function defaultWarehouse()
    {
        return $this->warehouses()->wherePivot('is_default', true)->first() ?? $this->warehouses()->first();
    }

    public function allowedWarehouseIds(): array
    {
        if ($this->hasRole(['super-admin', 'Admin', 'Super Admin'])) {
            return Warehouse::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return $this->warehouses()->pluck('warehouses.id')->map(fn ($id) => (int) $id)->all();
    }

    public function hasWarehouseAccess($warehouseId): bool
    {
        return in_array((int) $warehouseId, $this->allowedWarehouseIds(), true);
    }

    /**
     *  get all permissions users
     */
    public function getPermissions()
    {
        return $this->getAllPermissions()->mapWithKeys(function($permission){
            return [
                $permission['name'] => true
            ];
        });
    }

    /**
     * check role isSuperAdmin
     */
    public function isSuperAdmin()
    {
        return $this->hasRole('super-admin');
    }
}
