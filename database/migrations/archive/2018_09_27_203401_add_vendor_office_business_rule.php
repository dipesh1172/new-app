<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

use Carbon\Carbon;

class AddVendorOfficeBusinessRule extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $now = Carbon::now();
        $id = DB::table('business_rules')->insertGetId(
            [
                'created_at' => $now,
                'updated_at' => $now,
                'slug' => 'vendor_can_add_offices',
                'business_rule' => 'Vendors may add their own offices',
                'answers' => json_encode(
                    [
                        'type' => 'switch',
                        'on' => 'Yes',
                        'off' => 'No',
                        'default' => 'No',
                    ]
                ),
            ]
        );

        DB::table('business_rule_defaults')->insert(
            [
                'created_at' => $now,
                'updated_at' => $now,
                'business_rule_id' => $id,
                'default_answer' => 'No'
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
        //
    }
}
