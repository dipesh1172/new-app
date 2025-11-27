<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Carbon\Carbon;

class SetDefaultsForNewRules1 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $now = Carbon::now();
        DB::table('business_rule_defaults')->insert(
            [
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                    'business_rule_id' => 13,
                    'default_answer' => 'Clear Yes or No'
                ],
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                    'business_rule_id' => 14,
                    'default_answer' => 'false'
                ],
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                    'business_rule_id' => 15,
                    'default_answer' => 'false'
                ]
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
        
    }
}
