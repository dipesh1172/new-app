<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AddUserProfileFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('genders', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->timestamps();
            $table->softDeletes();
            $table->string('gender', 36)->nullable();
        });

        $now = Carbon::now();
        DB::table('genders')->insert(
            [
                [
                    'id' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'gender' => 'Male',
                ],
                [
                    'id' => 2,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'gender' => 'Female',
                ],
            ]
        );

        Schema::create('brand_user_notes', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->timestamps();
            $table->softDeletes();
            $table->string('brand_user_id', 36)->nullable();
            $table->string('added_by_brand_user_id', 36)->nullable();
            $table->text('note')->nullable();
        });

        Schema::create('brand_user_messages', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->timestamps();
            $table->softDeletes();
            $table->string('brand_user_id', 36)->nullable();
            $table->string('added_by_brand_user_id', 36)->nullable();
            $table->text('note')->nullable();
        });

        Schema::table('brand_users', function (Blueprint $table) {
            $table->tinyInteger('pass_bg_chk')->default(0)->nullable();
            $table->tinyInteger('pass_drug_test')->default(0)->nullable();
            $table->tinyInteger('pass_exam')->default(0)->nullable();
            $table->integer('gender_id')->nullable();
            $table->integer('language_id')->nullable();
            $table->string('ssn', 16)->nullable();
            $table->string('govt_id', 16)->nullable();
            $table->string('shirt_size', 8)->nullable();
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
