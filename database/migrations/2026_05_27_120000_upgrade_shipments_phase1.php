<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration{public function up(): void {
Schema::table('shipments',function(Blueprint $t){if(!Schema::hasColumn('shipments','dispatch_id'))$t->unsignedBigInteger('dispatch_id')->nullable()->after('warehouse_id');if(!Schema::hasColumn('shipments','delivery_status'))$t->enum('delivery_status',['pending','shipped','delivered'])->nullable()->after('status');foreach(['driver_name'=>150,'vehicle_no'=>100,'courier_name'=>150,'tracking_number'=>150] as $c=>$l){if(!Schema::hasColumn('shipments',$c))$t->string($c,$l)->nullable();}if(!Schema::hasColumn('shipments','cancelled_by'))$t->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();if(!Schema::hasColumn('shipments','cancelled_at'))$t->timestamp('cancelled_at')->nullable();if(!Schema::hasColumn('shipments','cancel_reason'))$t->string('cancel_reason',500)->nullable();});
Schema::table('shipment_lines',function(Blueprint $t){if(!Schema::hasColumn('shipment_lines','qty_ordered'))$t->decimal('qty_ordered',18,4)->default(0);if(!Schema::hasColumn('shipment_lines','qty_already_shipped'))$t->decimal('qty_already_shipped',18,4)->default(0);if(!Schema::hasColumn('shipment_lines','notes'))$t->text('notes')->nullable();});
}
public function down(): void {}}
