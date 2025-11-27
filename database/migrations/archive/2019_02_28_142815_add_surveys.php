<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Carbon\Carbon;

class AddSurveys extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('phone_number_types')->insert(
            [
                [
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'phone_number_type' => 'Survey'
                ],
            ]
        );

        DB::table('email_address_types')->insert(
            [
                [
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'email_address_type' => 'Survey'
                ],
            ]
        );

        Schema::create(
            'surveys',
            function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->timestamps();
                $table->softDeletes();
                $table->string('brand_id', 36);
                $table->string('script_id', 36)->nullable();
                $table->string('customer_first_name', 64)->nullable();
                $table->string('customer_last_name', 64)->nullable();
                $table->timestamp('customer_enroll_date')->nullable();
            }
        );

        Schema::table(
            'events',
            function (Blueprint $table) {
                $table->string('survey_id', 36)->after('lead_id')->nullable();
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
