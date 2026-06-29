<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset du cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Permissions ──────────────────────────────────────────────────
        $permissions = [
            // Phase 2
            'property.create',
            'property.update',
            'property.delete',
            'property.view',
            'contact.unlock',
            // Phase 3
            'property.verify',
            'support.manage',
            'signalement.manage',
            // Admin
            'admin.dashboard',
            'admin.users.manage',
            'admin.properties.moderate',
            'admin.complaints.manage',
            'subscription.manage',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'api']);
        }

        // ── Rôles ────────────────────────────────────────────────────────
        $owner = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'api']);
        $owner->syncPermissions([
            'property.create',
            'property.update',
            'property.delete',
            'property.view',
        ]);

        $seeker = Role::firstOrCreate(['name' => 'seeker', 'guard_name' => 'api']);
        $seeker->syncPermissions([
            'property.view',
            'contact.unlock',
        ]);

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        $admin->syncPermissions(Permission::all());

        $this->command->info('Rôles et permissions créés avec succès.');
    }
}
