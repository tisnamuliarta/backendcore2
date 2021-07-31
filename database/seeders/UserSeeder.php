<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\User;
use App\Models\UserApp;
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
        $user = User::create([
            'name' => 'manager',
            'username' => 'manager',
            'email' => 'sapb1@imip.co.id',
            'password' => bcrypt('*8Ultra')
        ]);

        $apps = Application::all();

        foreach ($apps as $app) {
            UserApp::create([
               'user_id' => $user->id,
                'app_id' => $app->id
            ]);
        }
    }
}
