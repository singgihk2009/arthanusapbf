<?php
namespace App\Models\Regulatory;
use App\Models\Inventory\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class RegulatoryProduct extends Model {
    public const TYPE_DRUG='DRUG';
    public const TYPE_MEDICAL_DEVICE='MEDICAL_DEVICE';
    protected $fillable=['source_id','product_type','nie','source_code','product_name_source','industry_name','raw_packaging_text','raw_composition_text','raw_payload','dosage_form','strength','commodity_type','license_type','registration_date','expiry_date','brand','sub_category','device_type','product_group','model_type','device_class','risk_class','registrant_name','registrant_address','manufacturer_name','manufacturer_address','manufacturer_name_2'];
    protected function casts(): array { return ['raw_payload'=>'array','registration_date'=>'date','expiry_date'=>'date']; }
    public function source(): BelongsTo { return $this->belongsTo(RegulatorySource::class,'source_id'); }
    public function compositions(): HasMany { return $this->hasMany(ProductComposition::class); }
    public function packagings(): HasMany { return $this->hasMany(ProductPackaging::class); }
    public function items(): BelongsToMany { return $this->belongsToMany(Item::class,'item_regulatory_products')->withPivot(['is_primary','notes','source_name','source_code'])->withTimestamps(); }
    public function isDrug(): bool { return $this->product_type===self::TYPE_DRUG; }
    public function isMedicalDevice(): bool { return $this->product_type===self::TYPE_MEDICAL_DEVICE; }
    public function normalizedNie(): ?string { return $this->normalizeNie((string)$this->nie); }
    public function licenseTypeDetected(): ?string { if($this->license_type) return strtoupper(trim((string)$this->license_type)); $nie=$this->normalizedNie() ?? ''; return str_starts_with($nie,'AKD') ? 'AKD' : (str_starts_with($nie,'AKL') ? 'AKL' : null); }
    public function licenseStatus(): string { if(!$this->expiry_date) return 'unknown'; $today=now()->startOfDay(); if($this->expiry_date->lt($today)) return 'expired'; if($this->expiry_date->lte($today->copy()->addDays(180))) return 'expiring_soon'; return 'active'; }
    public static function normalizeNie(?string $nie): ?string { if($nie===null) return null; return strtoupper(trim(preg_replace('/\s+/', ' ', $nie) ?? '')); }
}
