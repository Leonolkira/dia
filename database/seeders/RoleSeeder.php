<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::create(['name' => 'client']);
        Role::create(['name' => 'vendeur']);
        Role::create(['name' => 'admin']);
    }
}