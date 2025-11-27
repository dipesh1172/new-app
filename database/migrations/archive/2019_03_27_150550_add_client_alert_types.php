<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddClientAlertTypes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'client_alert_types',
            function (Blueprint $table) {
                $table->increments('id');
                $table->timestamps();
                $table->softDeletes();
                $table->string('client_alert_type', 64);
            }
        );

        DB::table('client_alert_types')->insert(
            [
                [
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'client_alert_type' => 'Standard'
                ],
                [
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'client_alert_type' => 'Additional Service'
                ],
            ]
        );

        Schema::table(
            'client_alerts',
            function (Blueprint $table) {
                $table->integer('client_alert_type_id')->default(1)->nullable();
            }
        );

        Schema::table(
            'client_alerts',
            function (Blueprint $table) {
                $table->integer('sort')->nullable();
            }
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
