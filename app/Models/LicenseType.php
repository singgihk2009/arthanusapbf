<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class LicenseType extends Model {use HasFactory; protected $fillable=['company_id','code','name','authority','expiry_required','document_required','is_active']; public function employeeLicenses(){return $this->hasMany(EmployeeLicense::class);} }
