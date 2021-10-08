<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user_item_groups = DB::table('user_itm_grps')
            ->where('user_id', '=', 18)
            ->get();

        $users = DB::table('users')->where('id', '<>', 18)->get();

        foreach ($users as $key => $value) {
            foreach ($user_item_groups as $key2 => $value2) {
                DB::table('user_itm_grps')
                    ->insert([
                        'user_id' => $value->id,
                        'item_group' => $value2->item_group,
                        'item_group_name' => $value2->item_group_name,
                        'db_code' => 'IMIP_LIVE',
                    ]);
            }
        }
    }
}
