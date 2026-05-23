<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table): void {
                $table->id();
                $table->string('customer_code', 50)->unique();
                $table->string('customer_name');
                $table->string('customer_type', 100)->nullable();
                $table->string('contact_person', 150)->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('email', 150)->nullable();
                $table->text('address')->nullable();
                $table->string('city', 150)->nullable();
                $table->string('province', 150)->nullable();
                $table->string('postal_code', 20)->nullable();
                $table->string('country', 150)->default('Indonesia');
                $table->string('npwp', 50)->nullable();
                $table->unsignedBigInteger('price_list_id')->nullable();
                $table->integer('payment_term_days')->default(0);
                $table->decimal('credit_limit', 18, 2)->default(0);
                $table->unsignedBigInteger('salesman_id')->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('customer_code');
                $table->index('customer_name');
                $table->index('status');
                $table->index('city');
                $table->index('phone');
                $table->index('email');
            });
        } else {
            Schema::table('customers', function (Blueprint $table): void {
                if (!Schema::hasColumn('customers', 'customer_code')) {
                    $table->string('customer_code', 50)->nullable()->after('id');
                }
                if (!Schema::hasColumn('customers', 'customer_name')) {
                    $table->string('customer_name')->nullable()->after('customer_code');
                }
                foreach ([
                    'customer_type' => ['string', 100], 'contact_person' => ['string', 150], 'province' => ['string', 150],
                    'postal_code' => ['string', 20], 'country' => ['string', 150], 'npwp' => ['string', 50],
                ] as $column => [$type, $length]) {
                    if (!Schema::hasColumn('customers', $column)) {
                        $table->{$type}($column, $length)->nullable();
                    }
                }
                if (!Schema::hasColumn('customers', 'city')) {
                    $table->string('city', 150)->nullable();
                }
                if (!Schema::hasColumn('customers', 'price_list_id')) {
                    $table->unsignedBigInteger('price_list_id')->nullable();
                }
                if (!Schema::hasColumn('customers', 'payment_term_days')) {
                    $table->integer('payment_term_days')->default(0);
                }
                if (!Schema::hasColumn('customers', 'credit_limit')) {
                    $table->decimal('credit_limit', 18, 2)->default(0);
                }
                if (!Schema::hasColumn('customers', 'salesman_id')) {
                    $table->unsignedBigInteger('salesman_id')->nullable();
                }
                if (!Schema::hasColumn('customers', 'status')) {
                    $table->enum('status', ['active', 'inactive'])->default('active');
                }
                if (!Schema::hasColumn('customers', 'notes')) {
                    $table->text('notes')->nullable();
                }
            });

            DB::table('customers')->whereNull('customer_code')->update(['customer_code' => DB::raw("CONCAT('CUST-', LPAD(id, 6, '0'))")]);
            if (Schema::hasColumn('customers', 'code')) {
                DB::table('customers')->whereNull('customer_code')->orWhere('customer_code', '')->update(['customer_code' => DB::raw('code')]);
            }
            if (Schema::hasColumn('customers', 'name')) {
                DB::table('customers')->whereNull('customer_name')->update(['customer_name' => DB::raw('name')]);
            }
            if (Schema::hasColumn('customers', 'is_active')) {
                DB::table('customers')->where('is_active', 0)->update(['status' => 'inactive']);
            }
            DB::table('customers')->whereNull('country')->update(['country' => 'Indonesia']);

            Schema::table('customers', function (Blueprint $table): void {
                $table->string('customer_code', 50)->nullable(false)->change();
                $table->string('customer_name')->nullable(false)->change();
                $table->string('country', 150)->default('Indonesia')->nullable(false)->change();
                $table->unique('customer_code', 'customers_customer_code_unique');
                $table->index('customer_code');
                $table->index('customer_name');
                $table->index('status');
                $table->index('city');
                $table->index('phone');
                $table->index('email');
            });
        }

        if (Schema::hasTable('price_lists')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->foreign('price_list_id')->references('id')->on('price_lists')->nullOnDelete();
            });
        }

        if (Schema::hasTable('salesmen')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->foreign('salesman_id')->references('id')->on('salesmen')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
    }
};

