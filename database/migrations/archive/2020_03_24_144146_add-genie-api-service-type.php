<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class AddGenieApiServiceType extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $now = now();
        DB::table('service_types')->insert([
            [
                'created_at' => $now,
                'updated_at' => $now,
                'name' => 'Genie Retail Vendor API',
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::table('service_types')->where('name', 'Genie Retail Vendor API')->delete();
    }
}
