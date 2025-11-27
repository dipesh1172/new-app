<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Carbon\Carbon;

class AddEnrollmentIntent extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->integer('enrollment_intent_id')->nullable();
        });

        Schema::create('enrollment_intents', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->timestamps();
            $table->softDeletes();
            $table->string('enrollment_intent', 36)->nullable();
        });

        $now = Carbon::now();
        DB::table('enrollment_intents')->insert(
            [
                [
                    'id' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'enrollment_intent' => 'Switch',
                ],
                [
                    'id' => 2,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'enrollment_intent' => 'Move-in',
                ],
                [
                    'id' => 3,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'enrollment_intent' => 'Move-in w/ Future Date',
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
