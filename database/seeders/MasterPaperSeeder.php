<?php

namespace Database\Seeders;

use App\Models\Paper;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterPaperSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table("master_papers")->insert([
            'name' => 'Surat Izin Masuk Kawasan',
            'alias' => 'sim'
        ]);

        DB::table("master_papers")->insert([
            'name' => 'Surat Izin Keluar Kawasan',
            'alias' => 'sik'
        ]);

        DB::table("master_papers")->insert([
            'name' => 'Surat Pengantar Rapid Masuk Kawasan',
            'alias' => 'srm'
        ]);

        DB::table("master_papers")->insert([
            'name' => 'Surat Pengantar Rapid Keluar Kawasan',
            'alias' => 'srk'
        ]);

        Paper::create(['for_self' => 'Y']);
    }
}
