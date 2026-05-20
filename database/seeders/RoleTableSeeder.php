<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $groups = [
            'users-access' => '%users%',
            'roles-access' => '%roles%',
            'permission-access' => '%permissions%',
            'inventory-master-access' => 'master-%',
            'inventory-reports-access' => 'report-%',
            'inventory-posting-access' => 'inventory-posting-%',
            'integration-access' => 'integration-%',
        ];

        foreach ($groups as $roleName => $likePattern) {
            $permissions = Permission::where('name', 'like', $likePattern)->get();
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($permissions);
        }

        $reportAccessRole = Role::firstOrCreate(['name' => 'inventory-reports-access']);
        $reportAccessRole->givePermissionTo(Permission::findOrCreate('inventory-reports-access', 'web'));

        Role::firstOrCreate(['name' => 'super-admin']);

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->givePermissionTo(Permission::whereIn('name', ['setup.company_profile.view','setup.company_profile.update','setup.company_profile.upload_logo'])->get());

        $stockkeeper = Role::firstOrCreate(['name' => 'Stockkeeper']);
        $stockkeeperPermissions = Permission::whereIn('name', [
            'dashboard-data','inventory.view','inventory.dashboard.view','inventory.stock_card.view','inventory.stock_ledger.view','inventory.movement.view',
            'inventory.receiving.view','inventory.receiving.create','inventory.receiving.update','inventory.adjustment.view','inventory.adjustment.create',
            'inventory.transfer.view','inventory.transfer.create','inventory.stock_opname.view','inventory.stock_opname.create','inventory.report.view'
        ])->get();
        $stockkeeper->syncPermissions($stockkeeperPermissions);
    }
}
