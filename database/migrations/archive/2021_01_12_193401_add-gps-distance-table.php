<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddGpsDistanceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gps_distances', function (Blueprint $table) {
            $table->string('id', 36)->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->string('type_id', 36);
            $table->integer('ref_type_id');
            $table->integer('distance_type_id');

            $table->string('gps_point_a', 36);
            $table->string('gps_point_b', 36);

            $table->float('distance', 16, 1);

            $table->index(['type_id', 'ref_type_id', 'distance_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gps_distances');
    }
}
