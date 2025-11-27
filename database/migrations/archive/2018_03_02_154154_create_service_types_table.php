<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class CreateServiceTypesTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('service_types', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->softDeletes()->index('idx_service_types_deleted_at');
            $table->string('name', 64)->nullable();
        });

        $dt = new DateTime;

        DB::table('service_types')->insert(
            [
                [
                    'created_at' => $dt->format('Y-m-d H:i:s'),
                    'updated_at' => $dt->format('Y-m-d H:i:s'),
                    'name' => 'Five9'
                ],
                [
                    'created_at' => $dt->format('Y-m-d H:i:s'),
                    'updated_at' => $dt->format('Y-m-d H:i:s'),
                    'name' => 'Amazon Connect'
                ],
                [
                    'created_at' => $dt->format('Y-m-d H:i:s'),
                    'updated_at' => $dt->format('Y-m-d H:i:s'),
                    'name' => 'Twilio'
                ],
                [
                    'created_at' => $dt->format('Y-m-d H:i:s'),
                    'updated_at' => $dt->format('Y-m-d H:i:s'),
                    'name' => 'Learn.TPV'
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
        Schema::drop('service_types');
    }
}
