<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class PaymentTermSeeder extends Seeder { public function run(): void { foreach([['NET0','Cash',0],['NET7','7 Days',7],['NET14','14 Days',14],['NET30','30 Days',30]] as $r){ DB::table('payment_terms')->updateOrInsert(['code'=>$r[0]],['name'=>$r[1],'days'=>$r[2],'is_active'=>1,'created_at'=>now(),'updated_at'=>now()]); } } }
