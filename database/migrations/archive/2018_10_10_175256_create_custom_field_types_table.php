<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Carbon\Carbon;

class CreateCustomFieldTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'custom_field_types', function (Blueprint $table) {
                $table->increments('id');
                $table->timestamps();
                $table->softDeletes();
                $table->string('name', 25)->unique();
                $table->string('description', 100);
            }
        );

        $timestamps = ['created_at' => Carbon::now(), 'updated_at' => Carbon::now()];

        DB::table('custom_field_types')->insert(
            [
                array_merge($timestamps, ['name' => 'Number', 'description' => 'Numerical Content']),
                array_merge($timestamps, ['name' => 'String', 'description' => 'Plain Text Content']),
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
        Schema::dropIfExists('custom_field_types');
    }
}
