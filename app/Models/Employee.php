<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;use Illuminate\Database\Eloquent\Model;
class Employee extends Model {use HasFactory; protected $fillable=['company_id','employee_code','nik','full_name','gender','birth_place','birth_date','phone','email','address','join_date','employment_status','department_id','position_id','warehouse_id','photo_path','signature_path','is_active']; protected $casts=['birth_date'=>'date','join_date'=>'date','is_active'=>'boolean'];
 public function company(){return $this->belongsTo(User::class,'company_id');} public function department(){return $this->belongsTo(Department::class);} public function position(){return $this->belongsTo(Position::class);} public function licenses(){return $this->hasMany(EmployeeLicense::class);} public function user(){return $this->hasOne(User::class);} }
