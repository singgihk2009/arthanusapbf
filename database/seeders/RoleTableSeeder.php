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
            'inventory-reports-access' => 'report-%',
            'inventory-posting-access' => 'inventory-posting-%',
        ];

        foreach ($groups as $roleName => $likePattern) {
            $permissions = Permission::where('name', 'like', $likePattern)->get();
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($permissions);
        }

        $reportAccessRole = Role::firstOrCreate(['name' => 'inventory-reports-access']);
        $reportAccessRole->givePermissionTo(Permission::findOrCreate('inventory-reports-access', 'web'));

        Role::firstOrCreate(['name' => 'super-admin']);
    }
}
