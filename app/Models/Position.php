<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Position extends Model {use HasFactory; protected $fillable=['company_id','name','level','description','is_active']; public function employees(){return $this->hasMany(Employee::class);} }
