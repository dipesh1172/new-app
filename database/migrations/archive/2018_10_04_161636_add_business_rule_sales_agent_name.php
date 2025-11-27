<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

use Carbon\Carbon;

class AddBusinessRuleSalesAgentName extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'client_alerts',
            function (Blueprint $table) {
                $table->tinyInteger('can_stop_call')
                    ->default(1);
            }
        );

        $now = Carbon::now();
        DB::table('client_alert_categories')->insert(
            [
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                    'id' => 5,
                    'name' => 'STANDALONE',
                    'display_name' => 'Individually Triggered',
                    'description' => 
                        'Alerts in this category are triggered independently',
                ],
            ]
        );

        $now = Carbon::now();
        DB::table('client_alerts')->insert(
            [
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                    'id' => 16,
                    'title' => 'Existing Sales Agent Name',
                    'channels' => 'DTD,TM',
                    'description' => 'Sends an alert if when creating a new Sales Agent, the Agent First and Last Name matches that of another Sales Agent, either Active or Inactive',
                    'threshold' => 0,
                    'function' => 'checkExistingSalesAgentName',
                    'category_id' => 5,
                ],
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
