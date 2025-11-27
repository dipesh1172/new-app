<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AddTimezones extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'timezones',
            function (Blueprint $table) {
                $table->increments('id');
                $table->timestamps();
                $table->softDeletes();
                $table->string('timezone', 64)->nullable();
            }
        );

        $now = Carbon::now();
        DB::table('timezones')->insert(
            [
                [
                    'id' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'timezone' => 'America/Chicago',
                ],
                [
                    'id' => 2,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'timezone' => 'America/Los_Angeles',
                ],
            ]
        );

        Schema::table('tpv_staff', function (Blueprint $table) {
            $table->integer('timezone_id')->default(1);
        });
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
