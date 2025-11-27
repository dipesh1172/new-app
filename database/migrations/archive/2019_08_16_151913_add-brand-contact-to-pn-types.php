<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddBrandContactToPnTypes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $now = now();
        $bc = DB::table('phone_number_types')->insert([
            'created_at' => $now,
            'updated_at' => $now,
            'phone_number_type' => 'Brand Contact',
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('phone_number_types')->where('phone_number_type', 'Brand Contact')->update(['deleted_at' => now()]);
    }
}
