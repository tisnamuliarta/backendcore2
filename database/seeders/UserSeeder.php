<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        User::create([
            'name' => 'manager',
            'username' => 'manager',
            'email' => 'sapb1@imip.co.id',
            'password' => bcrypt('*8Ultra')
        ]);
    }
}
