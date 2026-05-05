<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            ['code' => 'core_inventory', 'name' => 'Core Inventory', 'description' => 'Core inventory engine'],
            ['code' => 'procurement', 'name' => 'Procurement', 'description' => 'Procurement and purchasing'],
            ['code' => 'sales', 'name' => 'Sales', 'description' => 'Sales and delivery'],
            ['code' => 'pbf_compliance', 'name' => 'PBF Compliance', 'description' => 'Pharmaceutical and medical device compliance'],
            ['code' => 'kek_compliance', 'name' => 'KEK Compliance', 'description' => 'KEK facility and inventory compliance'],
        ];

        foreach ($modules as $module) {
            Module::query()->updateOrCreate(['code' => $module['code']], $module + ['is_active' => true]);
        }
    }
}
