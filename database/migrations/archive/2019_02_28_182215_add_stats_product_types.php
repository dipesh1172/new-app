<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

use Carbon\Carbon;

class AddStatsProductTypes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'stats_product_types',
            function (Blueprint $table) {
                $table->increments('id');
                $table->timestamps();
                $table->softDeletes();
                $table->string('stats_product_type', 64);
            }
        );

        DB::table('stats_product_types')->insert(
            [
                [
                    'id' => 1,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'stats_product_type' => 'TPV'
                ],
                [
                    'id' => 2,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'stats_product_type' => 'HRTPV'
                ],
                [
                    'id' => 3,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'stats_product_type' => 'Survey'
                ],
                [
                    'id' => 4,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'stats_product_type' => 'Agent Confirmation'
                ],
            ]
        );

        Schema::table(
            'stats_product',
            function (Blueprint $table) {
                $table->integer('stats_product_type_id')->default(1);
            }
        );

        Schema::table(
            'events',
            function (Blueprint $table) {
                $table->tinyInteger('agent_confirmation')->default(0);
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
