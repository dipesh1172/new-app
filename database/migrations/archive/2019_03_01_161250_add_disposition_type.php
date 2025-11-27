<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

use Carbon\Carbon;

class AddDispositionType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'disposition_types',
            function (Blueprint $table) {
                $table->increments('id');
                $table->timestamps();
                $table->softDeletes();
                $table->string('disposition_type', 64);
            }
        );

        Schema::table(
            'dispositions',
            function (Blueprint $table) {
                $table->integer('disposition_type_id')->default(1)->after('deleted_at');
            }
        );

        DB::table('disposition_types')->insert(
            [
                [
                    'id' => 1,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'disposition_type' => 'Energy'
                ],
                [
                    'id' => 2,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'disposition_type' => 'HRTPV'
                ],
                [
                    'id' => 3,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'disposition_type' => 'Survey'
                ],
                [
                    'id' => 4,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'disposition_type' => 'Agent Confirmation'
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
