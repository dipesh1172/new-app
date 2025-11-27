<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDXCLegacyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dxc_legacy', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamps();
            $table->timestamp('insert_at');
            $table->string('tpv_type', 3);
            $table->string('language', 11);
            $table->string('call_segments', 50);
            $table->string('confirmation_code', 24);
            $table->string('cic_call_id_keys');
            $table->string('brand');
            $table->float('call_time', 4, 2);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dxc_legacy');
    }
}
