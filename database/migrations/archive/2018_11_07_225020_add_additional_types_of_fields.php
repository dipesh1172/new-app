<?php

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;

class AddAdditionalTypesOfFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $now = Carbon::now();
        DB::table('custom_field_types')->insert(
            [
                ['id' => 3, 'created_at' => $now, 'updated_at' => $now, 'name' => 'Date', 'description' => 'Select a date from today forward.'],
                ['id' => 4, 'created_at' => $now, 'updated_at' => $now, 'name' => 'Picker', 'description' => 'Choose an option from the list.'],
            ]
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('custom_field_types')->where('id', '>', 2)->delete();
    }
}
