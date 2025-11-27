<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddDefaultIdTypes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $now = Carbon::now();
        DB::table('identification_types')->insert([
            ['name' => 'Driver\'s License', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'State ID', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Social Security Card', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Passport', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Matricula Consular', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Military ID', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Tribal ID', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Certificate of Naturalization/Citizenship', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Resident Alien ID', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // There is no down, only Zuul
    }
}
