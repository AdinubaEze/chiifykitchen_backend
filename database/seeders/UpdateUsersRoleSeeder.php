<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UpdateUsersRoleSeeder extends Seeder
{
    public function run()
    {
        // Update all existing users to have 'customer' role
        User::whereNull('role')->update(['role' => 'customer']);
        
        // Or if you need to set specific roles for some users
        // User::where('email', 'admin@example.com')->update(['role' => 'admin']);
    }
}