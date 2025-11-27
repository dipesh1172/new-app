<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Carbon\Carbon;

class AddCustomerListUploadType extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $now = Carbon::now();
        DB::table('upload_types')->insert([
            [
                'id' => 10,
                'created_at' => $now,
                'updated_at' => $now,
                'upload_type' => 'Customer List',
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::table('upload_types')->where('upload_type', 'Customer List')->delete();
    }
}
